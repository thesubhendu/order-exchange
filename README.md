# Order Exchange - Limit Order Exchange Mini Engine

A full-stack limit order exchange application built with Laravel (Backend API) and Vue.js (Frontend SPA). This project demonstrates financial data integrity, concurrency safety, scalable balance/asset management, and real-time order matching with Laravel Reverb broadcasting.

## 🛠 Technology Stack

- **Backend:** Laravel (latest stable)
- **Frontend:** Vue.js 3 (Composition API) + TypeScript
- **Database:** MySQL/PostgreSQL
- **Real-time:** Laravel Reverb via Laravel Broadcasting
- **State Management:** Pinia
- **Routing:** Vue Router
- **HTTP Client:** Axios

## 📋 Prerequisites

Before you begin, ensure you have the following installed:

- PHP >= 8.2
- Composer
- Node.js >= 18.x and npm
- MySQL/PostgreSQL
- Laravel Reverb (for real-time features)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/thesubhendu/order-exchange
cd order-exchange
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 5. Database Setup

Configure your database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_exchange
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations:

```bash
php artisan migrate
```

### 6. Seed Test Data (Optional)

To quickly test the application with pre-configured users, run the test data seeder:

```bash
php artisan db:seed --class=TestDataSeeder
```

This creates two test users:

**Buyer Account:**
- Email: `buyer@test.test`
- Password: `password`
- USD Balance: $100,000
- Use this account to test purchasing BTC or ETH

**Seller Account:**
- Email: `seller@test.test`
- Password: `password`
- USD Balance: $50,000
- Assets: 10 BTC, 100 ETH
- Use this account to test selling BTC or ETH

You can log in with either account to test order creation and matching functionality.

### 7. Laravel Reverb Configuration

Configure Laravel Reverb for real-time broadcasting. Add to `.env`:

```env
# Laravel Reverb Configuration
REVERB_APP_ID=your-reverb-app-id
REVERB_APP_KEY=your-reverb-app-key
REVERB_APP_SECRET=your-reverb-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Broadcasting Driver
BROADCAST_CONNECTION=reverb
```

Add frontend Reverb configuration (also in `.env`):

```env
VITE_REVERB_APP_KEY=your-reverb-app-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
VITE_API_URL=/api
```


### 8. Build Frontend Assets

For development:

```bash
npm run dev
```

For production:

```bash
npm run build
```

## 🏃 Running the Application

### Development Mode

Start Laravel development server:

```bash
php artisan serve
```

In another terminal, start Laravel Reverb server:

```bash
php artisan reverb:start
```

In a third terminal, start Vite dev server (if using `npm run dev`):

```bash
npm run dev
```

The application will be available at:
- **Frontend:** http://localhost:8000
- **API:** http://localhost:8000/api
- **Reverb WebSocket:** ws://localhost:8080

### Production Mode

Build assets and serve:

```bash
npm run build
php artisan serve
```

## 🔌 API Endpoints

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/register` | Register new user |
| `POST` | `/api/login` | Login user |

### Protected Endpoints (Requires Authentication)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/logout` | Logout user |
| `GET` | `/api/profile` | Get user profile with balances |
| `GET` | `/api/orders` | Get open orders (orderbook) |
| `GET` | `/api/orders/my` | Get user's orders (all statuses) |
| `POST` | `/api/orders` | Create limit order |
| `POST` | `/api/orders/{id}/cancel` | Cancel an order |
| `POST` | `/api/broadcasting/auth` | Reverb authentication |


## 🎨 Frontend Routes

| Route | Component | Description |
|-------|-----------|-------------|
| `/` | Orders.vue | Orders & Wallet Overview |
| `/login` | Login.vue | User login |
| `/register` | Register.vue | User registration |
| `/orders/new` | NewOrder.vue | Place new limit order |

## 💼 Core Features

### Order Matching Logic

- **Buy Order:** Matches with first SELL order where `sell.price <= buy.price`
- **Sell Order:** Matches with first BUY order where `buy.price >= sell.price`
- **Full Match Only:** No partial fills (as per requirements)
- **Price-Time Priority:** Orders matched by price, then by creation time

### Commission

- **Rate:** 1.5% of matched USD value
- **Deducted From:** Buyer (as per requirements)
- **Example:** 0.01 BTC @ $95,000 = $950 volume → $14.25 commission

### Balance Management

- **Buy Orders:** USD balance locked when order created
- **Sell Orders:** Asset amount moved to `locked_amount`
- **On Match:** 
  - Buyer receives assets
  - Seller receives USD (full execution price)
  - Commission deducted from buyer
  - Buyer refunded if order price > execution price

### Real-time Updates

- **Event:** `OrderMatched` broadcasted via Laravel Reverb
- **Channels:** `user.{buyer_id}` and `user.{seller_id}` (private channels)
- **Fallback:** Polling every 5 seconds if Reverb unavailable

## 🧪 Testing

### Running Tests

Run PHP tests:

```bash
php artisan test
```



## 🔒 Security Features

- **Authentication:** Laravel Sanctum token-based auth
- **Authorization:** Users can only cancel their own orders
- **Race Condition Protection:** Database row locking (`lockForUpdate()`)
- **Transaction Safety:** All critical operations wrapped in DB transactions
- **Input Validation:** All API endpoints validate input

## 🎯 Key Business Rules

1. **Buy Order Creation:**
   - Check `users.balance >= amount * price`
   - Deduct `amount * price` from `users.balance`
   - Order status: OPEN

2. **Sell Order Creation:**
   - Check `assets.amount - assets.locked_amount >= amount`
   - Move `amount` from `assets.amount` to `assets.locked_amount`
   - Order status: OPEN

3. **Order Matching:**
   - Trade executes at sell order price (price-time priority)
   - Buyer pays execution price + commission
   - Seller receives full execution price
   - Both orders marked as FILLED

4. **Order Cancellation:**
   - Buy orders: Refund locked USD to balance
   - Sell orders: Move locked assets back to available

