# Horizon PG

Horizon-PG brings the power of Laravel Horizon to PostgreSQL. It is specifically designed for high-performance queue monitoring using PostgreSQL instead of Redis, bridging the gap between database durability and Redis-like speed.

## 🚀 Extreme Performance

Through advanced PostgreSQL tuning, Horizon-PG achieves **Redis-level performance** while maintaining full ACID compliance.

| Metric | Horizon-PG (Optimized) | Redis (Standard) |
| :--- | :--- | :--- |
| **Throughput** | **~363 jobs/s** | ~507 jobs/s |
| **Latency** | **2.7ms** | 2.0ms |

### Key Optimizations:
- **UNLOGGED Tables**: Monitoring data (jobs, tags, metrics) uses `UNLOGGED` tables to bypass Write-Ahead Logging (WAL) for 14x faster writes.
- **FILLFACTOR = 70**: Tables are tuned for **HOT (Heap Only Tuple) Updates**, allowing status transitions (`pending` -> `reserved` -> `completed`) to happen entirely in memory.
- **Asynchronous Commits**: The connection is tuned with `synchronous_commit = off` for zero-latency transaction acknowledgement.

## 🛠 Installation

### 1. Require the Package
```bash
composer require emrullahardc/horizon-pg
```

### 2. Configure Environment
Update your `.env` to use the database queue driver:
```env
QUEUE_CONNECTION=database
```

### 3. Run Migrations
Horizon-PG includes optimized PostgreSQL migrations:
```bash
php artisan migrate
```

### 4. Start Horizon
```bash
php artisan horizon
```

## 🏗 High Parallelism
To match Redis performance, Horizon-PG is designed to scale horizontally across CPU cores. It is recommended to run at least **24 processes** on modern NVMe drives:

```php
// config/horizon.php
'processes' => 24,
```

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
