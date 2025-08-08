**Overview**
The Store Admin Web Application is a PHP + MySQL based management system that allows administrators to manage stores, vendors, and items from a central web interface.
It provides features to:
  View all stores and their inventories.
  Add new items to a store (with vendor linking and initial stock count).
  Update stock counts.
  Delete items (removing them globally if necessary).
  Automatically clean up vendors with no remaining supplies.
  Manage vendor and item IDs explicitly.

**Features:**

_Login Page_
Dummy login form (username & password required to access admin tools).

_Home Page_
Quick navigation to Stores, Tables, and other management features.

_Stores Page_

Lists all stores (name, address, and store ID).
Selecting a store shows its current inventory with:
  Item name, category, price, stock count.
  Option to update stock count.
  Option to delete an item everywhere (including vendor cleanup).
Add a new item to the selected store:
  Create a new vendor or select an existing vendor.
  Specify iId and vId manually (required).
  Provide item name, category, price, and initial stock.

_Tables Page_
Directly view database tables (read-only or editable, depending on setup).

**Technical Requirements**

Server: Apache with PHP 8+ (tested with XAMPP).
Database: MySQL 5.7+.
Browser: Chrome, Firefox, or Edge.

**Database Tables Used**

_store_ – Stores information.
_item_ – Items available for sale.
_store_item_ – Links stores to items with stock count.
_vendor_ – Vendor details.
_vendor_item_ – Links vendors to items.

**Installation**

Copy all files into your XAMPP htdocs directory (e.g., C:\xampp\htdocs\store).
Import the provided database SQL file into MySQL.
Update config.php with your MySQL credentials.
Start Apache and MySQL in XAMPP.
Access the site in your browser at: http://localhost/store

**Usage**

Login using the dummy credentials.
Navigate to Stores to manage inventory.
Add, update, or delete items as required.
Vendor cleanup is automatic when an item deletion leaves a vendor with no products.

**Important Notes**

iId and vId are manually entered when adding a new item/vendor link — ensure they are unique and correct.
Deleting an item removes it from all stores and cleans up unused vendors automatically.

All changes are live in the database immediately.
