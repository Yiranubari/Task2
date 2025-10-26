# HNG Stage 2: Country Currency & Exchange API

This is a RESTful API built with fundamental PHP for the HNG Internship (Stage 2). It fetches country and currency data from external sources, caches it in a MySQL database, and provides several endpoints for retrieving that data.

## Features

- **Data Caching:** Fetches data from two external APIs (`restcountries.com` and `open.er-api.com`) and stores it locally in a MySQL database.
- **CRUD Operations:** Provides endpoints to get all countries, get a single country by name, and delete a country.
- **Filtering & Sorting:** The `GET /countries` endpoint supports filtering by region, filtering by currency, and sorting by estimated GDP.
- **Status Endpoint:** A `GET /status` endpoint to monitor the total number of countries in the database and the last refresh time.
- **Dynamic Image Generation:** A `GET /countries/image` endpoint that serves a dynamically generated PNG image summarizing the API status, including the top 5 countries by estimated GDP.

## API Endpoints

| Method   | Endpoint             | Description                                                                       |
| :------- | :------------------- | :-------------------------------------------------------------------------------- |
| `POST`   | `/countries/refresh` | Fetches data from external APIs, calculates GDP, and updates the local database.  |
| `GET`    | `/countries`         | Gets all countries. Supports filters: `?region=`, `?currency=`, `?sort=gdp_desc`. |
| `GET`    | `/countries/:name`   | Gets a single country by its name (e.g., `/countries/Nigeria`).                   |
| `DELETE` | `/countries/:name`   | Deletes a single country by its name.                                             |
| `GET`    | `/status`            | Returns the total country count and the last refresh timestamp.                   |
| `GET`    | `/countries/image`   | Serves a PNG image with a summary of the database (total countries, top 5, etc.). |

## Setup and Installation

### 1. Prerequisites

- PHP (v7.4 or higher)
- MySQL
- Composer (optional, not required for this project)
- A tool to run the PHP server (like the built-in PHP server or XAMPP/WAMP)

### 2. Clone the Repository

```bash
git clone [https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git](https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git)
cd YOUR_REPO_NAME
```

3. Setup the Database
   Open your MySQL client (like phpMyAdmin).

Create a new database. The recommended name is stage2 or hng_stage2.

Select the new database and go to the SQL tab.

Copy and paste the contents of schema.sql into the query box and run it. This will create the countries and status tables.

4. Configure Environment
   Create a .env file in the root of the project: touch .env

Copy the contents of .env.example (if you have one) or add the following:

```bash
DB_HOST=localhost
DB_NAME=stage2
DB_USER=root
DB_PASSWORD=your_mysql_password
```

Update these values to match your local MySQL setup.

## How to Run Locally

This project is designed to run with the built-in PHP web server.

Navigate to the project's root directory in your terminal.

Run the following command:

```bash
php -S localhost:8000
```

your API will now be running at http://localhost:8000.

You can now use a tool like Bruno, Postman, or Insomnia to interact with the endpoints.

Example Base URL: http://localhost:8000/index.php

GET Status: GET http://localhost:8000/index.php/status

Refresh Data: POST http://localhost:8000/index.php/countries/refresh

Get Image: http://localhost:8000/index.php/countries/image (Open this in your browser)
