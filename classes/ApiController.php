<?php
declare(strict_types=1);

/**
 * HTTP layer: reads the request, calls FleetService, and answers in JSON.
 *
 *   GET  index.php?resource=all | vehicles | customers | rentals
 *   POST index.php?resource=vehicles|customers|rentals&action=create|update|delete[&id=...]
 *   POST index.php?resource=rentals&action=return&id=R-0001
 *   POST index.php?action=reset
 */
final class ApiController
{
    /** @var FleetService */
    private $fleet;
    /** @var FleetDataManager */
    private $data;

    public function __construct(FleetService $fleet, FleetDataManager $data)
    {
        $this->fleet = $fleet;
        $this->data  = $data;
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            // Every save is a read-modify-write, so requests take turns.
            $this->data->lock();

            $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $resource = (string) ($_GET['resource'] ?? 'all');
            $action   = (string) ($_GET['action'] ?? '');
            $id       = isset($_GET['id']) ? (string) $_GET['id'] : null;

            if ($method === 'GET') {
                $this->respond($this->read($resource));
            }
            if ($method !== 'POST') {
                throw new ApiException('Method ' . $method . ' is not supported. Use GET or POST.', 405);
            }

            if ($action === 'reset') {
                $this->respond(['ok' => true, 'message' => 'Demo data restored.', 'data' => $this->fleet->resetDemoData()]);
            }

            if (!in_array($resource, Config::COLLECTIONS, true)) {
                throw new NotFoundException('Unknown resource: ' . $resource);
            }

            $message = $this->mutate($resource, $action, $id, $this->body());
            $this->respond(['ok' => true, 'message' => $message, 'data' => $this->fleet->allData()]);
        } catch (ApiException $error) {
            $this->respond(['ok' => false, 'error' => $error->getMessage()], $error->getStatus());
        } catch (Throwable $error) {
            $this->respond(['ok' => false, 'error' => 'Server error: ' . $error->getMessage()], 500);
        }
    }

    private function read(string $resource): array
    {
        if ($resource === 'all') {
            return ['ok' => true, 'data' => $this->fleet->allData()];
        }
        return ['ok' => true, 'data' => [$resource => $this->fleet->collection($resource)]];
    }

    private function mutate(string $resource, string $action, ?string $id, array $input): string
    {
        switch ($action) {
            case 'create':
                return $this->save($resource, $input, null);

            case 'update':
                return $this->save($resource, $input, $this->requireId($id, 'update a record'));

            case 'delete':
                $id = $this->requireId($id, 'delete a record');
                if ($resource === 'vehicles') {
                    return $this->fleet->deleteVehicle($id);
                }
                return $resource === 'customers' ? $this->fleet->deleteCustomer($id) : $this->fleet->deleteRental($id);

            case 'return':
                if ($resource === 'rentals') {
                    return $this->fleet->recordReturn($this->requireId($id, 'record a return'));
                }
                break;
        }

        throw new NotFoundException('Unknown action: ' . $action);
    }

    private function save(string $resource, array $input, ?string $id): string
    {
        if ($resource === 'vehicles') {
            return $this->fleet->saveVehicle($input, $id);
        }
        return $resource === 'customers' ? $this->fleet->saveCustomer($input, $id) : $this->fleet->saveRental($input, $id);
    }

    private function requireId(?string $id, string $purpose): string
    {
        if ($id === null || $id === '') {
            throw new ValidationException('An id is required to ' . $purpose . '.');
        }
        return $id;
    }

    /** Decoded JSON request body; an empty body is an empty record, a malformed one is a 400. */
    private function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $input = json_decode($raw, true);
        if (!is_array($input)) {
            throw new ApiException('The request body is not valid JSON.', 400);
        }
        return $input;
    }

    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json !== false ? $json : '{"ok":false,"error":"Could not encode the response."}';
        exit;
    }
}
