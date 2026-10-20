# Project: Restaurant POS (Kedai Soto Banso), university assignment

Goal: replicate the lecturer's reference website as closely as possible.
Reference screenshots are in /reference (26 files, there is no HTML source).

Stack (mandatory): Laravel on Laravel Herd, Livewire 4, Microsoft SQL Server
only (sqlsrv driver, localhost\SQLEXPRESS, DBs pos_restaurant_sabah and
pos_restaurant_sabah_test). Never use SQLite, MySQL or PostgreSQL. Pest tests
must run against SQL Server.

Conventions:
- Reuse shared components: <x-card>, <x-table>, <x-modal>.
- Order statuses are only: new, pre_order, paid, cancelled.
- Payment info lives only in the payments table (not on orders).
- order_items keeps snapshots (item_code, item_description) and modifiers JSON.
- Avoid SQL Server "multiple cascade paths" errors.
- Server recomputes all prices; never trust browser values.
- Order # = orders.id. Currency is RM (RM14.00 format).
- Never leave placeholder markers like __SOMETHING_START__ in Blade files.
- Commit per milestone, run the full test suite before committing.

Done so far: M0-M8 (setup, shell, dashboard, categories, items with photos
and modifiers, tables and QR, POS grid, Add Item modal, order lines).
Customer QR ordering, inventory, reports and Test Drawing are out of scope.
