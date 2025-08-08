# Store Admin Web Application

---

## 📌 Overview
The **Store Admin Web Application** is a PHP + MySQL based management system that allows administrators to manage stores, vendors, and items from a central web interface.  

**Core Functions:**
- View all stores and their inventories.
- Add new items to a store (with vendor linking and initial stock count).
- Update stock counts.
- Delete items globally, including vendor cleanup.
- Explicitly manage `iId` and `vId`.

---

## ✨ Features

### 1. Login Page
- Simple login form to access admin tools.

### 2. Home Page
- Quick navigation to **Stores**, **Tables**, and other management features.

### 3. Stores Page
- List all stores with store ID.
- Select a store to view inventory:
  - Item name, category, price, stock count.
  - Update stock count.
  - Delete item everywhere (removes from all stores & cleans unused vendors).
- Add new item to a store:
  - Create new vendor **or** choose an existing vendor.
  - Manually set `iId` and `vId`.
  - Provide name, category, price, and initial stock.

### 4. Tables Page
- View database tables directly (read-only or editable depending on setup).

---

## 🛠 Technical Requirements
- **Server:** Apache with PHP 8+ (tested with XAMPP)
- **Database:** MySQL 5.7+
- **Browser:** Chrome, Firefox, or Edge

---

## 📂 Database Tables
- `store` – Stores information.
- `item` – Items available for sale.
- `store_item` – Links stores to items with stock count.
- `vendor` – Vendor details.
- `vendor_item` – Links vendors to items.

---

## 🚀 Installation
1. Copy all files into your XAMPP `htdocs` directory:
   ```bash
   C:\xampp\htdocs\store

2. Import the provided database `.sql` file into MySQL.

3. Update `config.php` with your MySQL credentials.

4. Start Apache and MySQL in XAMPP.

5. Access the site in your browser: `http://localhost/store`

## 📖 Usage

1. Login using provided credentials.

2. Go to Stores to manage inventory.

3. Add, update, or delete items as needed.

4. Vendor cleanup happens automatically when their last item is removed.

## ⚠️ Notes

- iId and vId must be entered manually when adding new items/vendors.

- Deleting an item removes it from all stores and deletes unused vendors.

- All database changes are immediate.


