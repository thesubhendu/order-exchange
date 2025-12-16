# Order Exchange - Limit Order Exchange Mini Engine

A full-stack limit order exchange application built with Laravel (Backend API) and Vue.js (Frontend SPA). This project demonstrates financial data integrity, concurrency safety, scalable balance/asset management, and real-time order matching with Pusher broadcasting.

## 🛠 Technology Stack

- **Backend:** Laravel (latest stable)
- **Frontend:** Vue.js 3 (Composition API) + TypeScript
- **Database:** MySQL/PostgreSQL
- **Real-time:** Pusher via Laravel Broadcasting
- **State Management:** Pinia
- **Routing:** Vue Router
- **HTTP Client:** Axios

## 📋 Prerequisites

Before you begin, ensure you have the following installed:

- PHP >= 8.2
- Composer
- Node.js >= 18.x and npm
- MySQL/PostgreSQL
- Pusher account (for real-time features)

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

### 6. Pusher Configuration

Get your Pusher credentials from [pusher.com](https://pusher.com) and add them to `.env`:

```env
# Pusher Configuration
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_KEY=your-pusher-app-key
PUSHER_APP_SECRET=your-pusher-app-secret
PUSHER_APP_CLUSTER=mt1

# Broadcasting Driver
BROADCAST_DRIVER=pusher
```

Add frontend Pusher configuration (also in `.env`):

```env
VITE_PUSHER_APP_KEY=your-pusher-app-key
VITE_PUSHER_APP_CLUSTER=mt1
VITE_API_URL=/api
```

### 7. Build Frontend Assets

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

In another terminal, start Vite dev server (if using `npm run dev`):

```bash
npm run dev
```

The application will be available at:
- **Frontend:** http://localhost:8000
- **API:** http://localhost:8000/api

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
| `POST` | `/api/broadcasting/auth` | Pusher authentication |

### Request/Response Examples

#### Register User

```bash
POST /api/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Create Limit Order

```bash
POST /api/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "symbol": "BTC",
  "side": "buy",
  "price": 95000.00,
  "amount": 0.01
}
```

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

- **Event:** `OrderMatched` broadcasted via Pusher
- **Channels:** `private-user.{buyer_id}` and `private-user.{seller_id}`
- **Fallback:** Polling every 5 seconds if Pusher unavailable

## 🧪 Testing

Run PHP tests:

```bash
php artisan test
```

Run specific test suite:

```bash
php artisan test --testsuite=Feature
```

## 🔒 Security Features

- **Authentication:** Laravel Sanctum token-based auth
- **Authorization:** Users can only cancel their own orders
- **Race Condition Protection:** Database row locking (`lockForUpdate()`)
- **Transaction Safety:** All critical operations wrapped in DB transactions
- **Input Validation:** All API endpoints validate input

## 🐛 Troubleshooting

### Pusher Not Working

1. Verify Pusher credentials in `.env`
2. Check `BROADCAST_DRIVER=pusher`
3. Verify channel authorization in browser console
4. Check Laravel logs: `storage/logs/laravel.log`

### API Calls Failing

1. Verify `VITE_API_URL` is set correctly
2. Check Laravel CORS configuration
3. Ensure Sanctum middleware is working
4. Verify token is included in Authorization header

### Real-time Updates Not Showing

1. Check Pusher connection in browser console
2. Verify channel subscription
3. Check Laravel broadcasting logs
4. Ensure user is authenticated

### Frontend Build Issues

1. Clear node modules and reinstall: `rm -rf node_modules && npm install`
2. Clear Vite cache: `rm -rf node_modules/.vite`
3. Rebuild: `npm run build`

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

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/)
- [Pusher Documentation](https://pusher.com/docs)
- [Pinia Documentation](https://pinia.vuejs.org/)

## 📄 License

[Your License Here]

## 👥 Contributors

[Your Name/Team]

---

For detailed API app setup instructions, see [API_APP_SETUP.md](./API_APP_SETUP.md)
