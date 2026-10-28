# FleetFlow — Vehicle Rental Management System (HTML + CSS + PHP)

The same system you see in the hosted app as plain HTML, CSS, JavaScript and an object-oriented
PHP backend split into classes, interfaces, traits and exceptions. No frameworks, no build step
and no database — records are stored in one JSON file that the backend creates on first run. There is no login screen.

## Requirements
PHP 7.4 or newer (PHP 8 recommended). Nothing else — the front end is plain HTML/CSS/JS.

## Running it
Copy the whole folder into your web root and open `index.php` (or just the folder) in a browser.

* **XAMPP / WAMP / MAMP** — copy the folder into `htdocs` (XAMPP) or `www` (WAMP), start Apache,
  then open `http://localhost/fleetflow/`.
* **PHP's built-in server** — from inside the folder run:
  ```
  php -S localhost:8000
  ```
  then open `http://localhost:8000/`.

Opening `index.html` directly from the file system (a `file://` address) will show a notice:
the screens need PHP to load their data.

On first run the backend writes `data/records.json` (vehicles, customers and rentals in one
file), seeded with the demo fleet, customers and rentals. If you are upgrading from the
single-file version, the old `vehicles.json`, `customers.json` and `rentals.json` are imported
automatically. Keep that `data`
folder writable by the web server. "Reset demo data" at the bottom of the sidebar restores
the original seed records.

## Console version (menu-driven)

The same system also runs in a terminal. From inside the folder:

```
php index.php
```

```
====================================
   FLEETFLOW - VEHICLE RENTAL SYSTEM
====================================

1. Add Record          5. Delete Record
2. View All Records    6. Process / Calculate
3. Search Record       7. Save Records
4. Update Record       8. Load Records
                       9. Exit
```

Records are vehicles, customers or rentals (you pick the type for options 1-5). Option 6 gives a
rental quote, records a return with its late fee, or compares how Car, Motorcycle and Van price the
same rental. Changes stay in memory until option 7 (or "yes" when exiting) saves them to
`data/records.json`; option 8 reloads the file and discards unsaved changes. Errors are caught and
shown as `Error: ...`, and "Operation completed." is printed after every action.

The web app and the console share the same classes and the same `data/records.json`.

## Files
```
fleetflow/
├── index.php                  Entry point: console menu (php index.php) or web app + API
├── index.html, styles.css     Front end screens and responsive styling
├── app.js, charts.js          Front end logic and dependency-free SVG charts
├── classes/
│   ├── Vehicle.php              ABSTRACT parent: private data, getters/setters, pricing
│   ├── Car.php                  child: 10% off from 7 days
│   ├── Motorcycle.php           child: 5% off from 3 days
│   ├── Van.php                  child: 15% off from 7 days
│   ├── VehicleFactory.php       picks the right child class for a category
│   ├── FleetDataManager.php     FILE/DATA MANAGEMENT: load, save, delete file, in-memory records
│   ├── Repository.php           abstract parent for the three record collections
│   ├── VehicleRepository.php    ┐
│   ├── CustomerRepository.php   ├ children of Repository
│   ├── RentalRepository.php     ┘
│   ├── FleetService.php         Business rules: validation, rentals, double-booking, statuses
│   ├── PricingCalculator.php    Rental days, late fees, payment status
│   ├── ConsoleApp.php           The numbered console menu
│   ├── ApiController.php        HTTP layer: routing, request body, JSON responses
│   ├── Config.php               Allowed values, late-fee rate, paths, timezone
│   └── SeedData.php             Demo records
├── interfaces/
│   ├── RentableInterface.php    isAvailable(), calculateRentalCost()
│   ├── ExportableInterface.php  toArray()
│   ├── RepositoryInterface.php
│   └── Seedable.php
├── traits/
│   ├── TimestampTrait.php       created_at / updated_at (used by Vehicle)
│   ├── JsonFileStorage.php      Atomic reading and writing of records.json
│   ├── DateHelpers.php          Date parsing and day arithmetic
│   └── ValidatesInput.php       Safe reading of request fields
├── exceptions/
│   ├── ApiException.php         Base error carrying an HTTP status
│   ├── ValidationException.php  422
│   ├── NotFoundException.php    404
│   └── StorageException.php     500
└── data/
    └── records.json             Created on first run; Reset demo data restores it
```

## OOP concepts: where to find each one

| # | Concept | Where |
| --- | --- | --- |
| 1 | Classes and objects | `index.php` builds `FleetDataManager`, three repositories, `FleetService`; `VehicleFactory` creates `Car`/`Motorcycle`/`Van` objects |
| 2 | Properties and methods | `Vehicle.php` (private properties; `calculateRentalCost()`, `describe()`, `toArray()`) |
| 3 | Constructors and destructors | `Vehicle::__construct`, `FleetDataManager::__construct` and `FleetDataManager::__destruct` (prints "Data file released" when the console app ends) |
| 4 | Access modifiers | `public` getters/setters, `private` data in `Vehicle`, `protected` `discountRate()` and `Repository::$data` |
| 5 | Getters and setters | `Vehicle::getBrand()` / `setBrand()` and the rest; setters validate and throw |
| 6 | Encapsulation | `Vehicle` data is private and only changes through validating setters; `FleetDataManager` hides the file behind `load()`/`save()` |
| 7 | Inheritance | `Car`, `Motorcycle`, `Van` extend `Vehicle`; the repositories extend `Repository` |
| 8 | Method overriding | Each child overrides `Vehicle::describe()` and its constructor |
| 9 | `parent` keyword | `parent::__construct($data)` and `parent::describe()` in `Car.php`, `Motorcycle.php`, `Van.php` |
| 10 | Abstract class | `Vehicle` and `Repository` |
| 11 | Abstract methods | `Vehicle::getCategory()` and `discountRate()`; `Repository::section()`, `idKey()`, `idPrefix()` |
| 12 | Runtime polymorphism | Console 6.3 (`FleetService::compareCategories()`): one `calculateRentalCost()` call, three different totals; `describe()` differs per child |
| 13 | Interface | `RentableInterface`, `ExportableInterface`, `RepositoryInterface`, `Seedable` |
| 14 | Multiple interfaces | `Vehicle implements RentableInterface, ExportableInterface`; `Repository implements RepositoryInterface, Seedable` |
| 15 | Trait | `TimestampTrait` (in `Vehicle`), `JsonFileStorage` (in `FleetDataManager`), `DateHelpers`, `ValidatesInput` |
| 16 | Exception handling | `try` / `catch` / `finally` / `throw` in `ConsoleApp::run()`; `throw` throughout `FleetService` and the setters |
| 17 | Custom exceptions | `ValidationException`, `NotFoundException`, `StorageException` (all extend `ApiException`) |
| 18 | File operations | `FleetDataManager`: create/write (`save()`), read/load (`load()`), update (`setSection()`), delete (`deleteFile()`), loading saved records at start-up |

Minimum structure: abstract parent `Vehicle`; child classes `Car`, `Motorcycle`, `Van`; interfaces
above; `Vehicle` and `Repository` each implement two interfaces; traits above; custom exceptions
above; data class `FleetDataManager`.

All artwork is inline SVG (logo, navigation icons, action icons, favicon) — no image files
and no emoji.

## Backend API (`index.php`)
| Method | Request | Purpose |
| --- | --- | --- |
| GET | `index.php?resource=all` | Every collection in one response |
| GET | `index.php?resource=vehicles` | Vehicle list (also `customers`, `rentals`) |
| POST | `index.php?resource=vehicles&action=create` | Add a vehicle (JSON body) |
| POST | `index.php?resource=vehicles&action=update&id=V-002` | Update a vehicle |
| POST | `index.php?resource=vehicles&action=delete&id=V-002` | Delete a vehicle |
| POST | `index.php?resource=customers&action=create\|update\|delete` | Same pattern for customers |
| POST | `index.php?resource=rentals&action=create\|update\|delete` | Same pattern for rentals |
| POST | `index.php?resource=rentals&action=return&id=R-0001` | Close a rental with today's return date |
| POST | `index.php?action=reset` | Restore the demo records |

Responses are JSON: `{"ok": true, "message": "...", "data": {...collections}}`, or
`{"ok": false, "error": "..."}` with an HTTP 4xx status when the request is rejected.

## Business rules (enforced by `FleetService`, the `Vehicle` classes and `PricingCalculator`)
* Rental days = calendar days between start and expected return, minimum 1 day.
* Volume discounts: Car 10% from 7 days, Motorcycle 5% from 3 days, Van 15% from 7 days.
* Late fee: 20% of the daily rate for each day past the expected return date.
* Payment status is derived from the amount paid: Paid, Partial or Unpaid.
* New rentals are Active when the start date is today or earlier, otherwise Reserved.
* A vehicle cannot be double-booked — overlapping Reserved or Active bookings are rejected.
* Vehicles or customers with rentals attached cannot be deleted.
* Vehicle status (Available / Rented / Reserved / Maintenance) follows its live bookings.

## Screens
* **Dashboard** — fleet, live rental, customer and revenue figures, a six-month revenue
  trend, fleet status donut, recent rentals and the most rented vehicles.
* **Vehicles** — search and filter the fleet, add, edit and delete vehicles.
* **Customers** — search renter records, add, edit and delete customers.
* **Rentals** — search and filter bookings, create and edit rentals with a live charge
  breakdown, record a return (late fee included) and delete bookings.
* **Reports** — choose a date range for revenue and vehicle utilization, then print or save
  it as a PDF from the browser print dialog.

## Customizing
* **Colours and fonts** — the custom properties in `:root` at the top of `styles.css`
  (Inter and Poppins are loaded from Google Fonts in `index.html`).
* **Seed records** — `classes/SeedData.php`; delete `data/records.json` so it is written again.
* **Pricing and timezone** — each category's discount is a pair of constants at the top of `Car.php`, `Motorcycle.php` and `Van.php`; `LATE_FEE_RATE` and `TIMEZONE` are in `classes/Config.php`. Keep the
  matching values in `app.js` (used for the live charge preview in the rental form) in step.
