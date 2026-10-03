<h1 align="center">Laravel M-Pesa Daraja STK Push</h1>

<p align="center">
  A clean, beginner-friendly example of integrating Safaricom's <b>M-Pesa Daraja API</b> (Lipa na M-Pesa Online / STK Push) into <b>Laravel 12</b>.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/M--Pesa-Daraja%20API-4CAF50" alt="M-Pesa Daraja">
  <img src="https://img.shields.io/badge/License-MIT-blue" alt="MIT License">
</p>

---

## Table of Contents

- [About](#about)
- [Features](#features)
- [How It Works](#how-it-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Testing the Callback with ngrok](#testing-the-callback-with-ngrok)
- [Project Structure](#project-structure)
- [Troubleshooting](#troubleshooting)
- [Going Live](#going-live)
- [Roadmap](#roadmap)
- [Security](#security)
- [Looking for Collaborators](#looking-for-collaborators)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)
- [Author](#author)

---

## About

This project shows, step by step, how to accept M-Pesa payments in a Laravel application using the Daraja **STK Push** (M-Pesa Express) API. The customer receives a payment prompt on their phone, enters their M-Pesa PIN, and Safaricom notifies your application of the result through a callback.

The code is deliberately small and readable, so you can learn from it or copy it into your own project.

## Features

- OAuth access token request using your Consumer Key and Secret
- STK Push request with automatic password and timestamp generation
- Phone number normalisation (`0712...`, `+254712...` and `712...` all become `254712...`)
- Callback (webhook) endpoint that receives and logs the payment result
- Sandbox and live environments, switched with a single `.env` value
- All credentials kept in `.env`, read through Laravel's config system
- A dedicated service class that keeps controllers thin and code reusable

## How It Works

```
 Customer's phone          Your Laravel app                Safaricom Daraja
        |                         |                               |
        |                         |--- 1. Request access token -->|
        |                         |<-- access token --------------|
        |                         |                               |
        |                         |--- 2. STK Push request ------>|
        |                         |<-- "Request accepted" --------|
        |<---- 3. PIN prompt -----------------------------------|
        |---- enters PIN -------------------------------------->|
        |                         |                               |
        |                         |<-- 4. Callback (result) ------|
        |                         |--- 5. Respond "Accepted" ---->|
```

Note that "Request accepted" in step 2 only means Safaricom received your request. The final payment result arrives in step 4, through the callback.

## Requirements

- PHP **8.2** or higher
- [Composer](https://getcomposer.org/)
- A Safaricom Developer account at [developer.safaricom.co.ke](https://developer.safaricom.co.ke)
- [ngrok](https://ngrok.com/) (or any public HTTPS tunnel) for testing the callback locally

## Installation

```bash
# 1. Clone the repository
git clone https://github.com/Dantechdevs/laravel-mpesa-daraja-stk-push.git
cd laravel-mpesa-daraja-stk-push

# 2. Install dependencies
composer install

# 3. Create your environment file and app key
cp .env.example .env
php artisan key:generate

# 4. Start the development server
php artisan serve
```

The app is now running at `http://127.0.0.1:8000`.

## Configuration

### 1. Get your Daraja credentials

1. Log in at [developer.safaricom.co.ke](https://developer.safaricom.co.ke).
2. Go to **My Apps**, create an app and enable the **M-Pesa Sandbox** API.
3. Copy the **Consumer Key** and **Consumer Secret** from the app page.
4. Under **APIs → M-Pesa Express → Simulate**, copy the sandbox **Passkey**.

### 2. Add them to `.env`

```dotenv
# M-Pesa Daraja
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your_passkey
MPESA_ENVIRONMENT=sandbox
MPESA_CALLBACK_URL=https://your-ngrok-url/api/mpesa/callback
```

| Variable | Description |
|---|---|
| `MPESA_CONSUMER_KEY` | Your app's username for Safaricom |
| `MPESA_CONSUMER_SECRET` | Your app's password for Safaricom |
| `MPESA_SHORTCODE` | Business shortcode. `174379` is the sandbox default |
| `MPESA_PASSKEY` | Used to generate the password for each STK request |
| `MPESA_ENVIRONMENT` | `sandbox` for testing, `live` for real payments |
| `MPESA_CALLBACK_URL` | A **public HTTPS** URL where Safaricom sends the payment result |

### 3. Clear the config cache

```bash
php artisan config:clear
```

The variables are registered in `config/services.php` under the `mpesa` key and read in code with `config('services.mpesa.key')`.

## Usage

### Send an STK Push

**Endpoint:** `POST /api/mpesa/pay`

```bash
curl -X POST http://127.0.0.1:8000/api/mpesa/pay \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"phone": "254708374149", "amount": 1}'
```

**Successful response:**

```json
{
  "MerchantRequestID": "xxxx-xxxx-xxxx",
  "CheckoutRequestID": "ws_CO_xxxxxxxxxxxxxxxx",
  "ResponseCode": "0",
  "ResponseDescription": "Success. Request accepted for processing",
  "CustomerMessage": "Success. Request accepted for processing"
}
```

`ResponseCode: "0"` means Safaricom accepted the request. Keep the `CheckoutRequestID`, because it identifies this payment in the callback.

### Receive the callback

**Endpoint:** `POST /api/mpesa/callback`

Safaricom calls this URL after the customer pays, cancels or times out. The result is written to `storage/logs/laravel.log`. A `ResultCode` of `0` means the payment succeeded.

### Use the service in your own code

```php
use App\Services\MpesaService;

$response = app(MpesaService::class)->stkPush(
    phone: '0712345678',
    amount: 100,
    reference: 'ORDER001'
);
```

## Testing the Callback with ngrok

Safaricom cannot reach `localhost`, so expose your server with a tunnel:

```bash
php artisan serve
ngrok http 8000
```

Copy the `https://...` address ngrok shows, then update `.env`:

```dotenv
MPESA_CALLBACK_URL=https://your-ngrok-url.ngrok-free.app/api/mpesa/callback
```

```bash
php artisan config:clear
```

Watch the log for incoming callbacks:

```bash
php artisan pail
# or
tail -f storage/logs/laravel.log
```

## Project Structure

```
.
├── app
│   ├── Http
│   │   └── Controllers
│   │       └── MpesaController.php    # pay() and callback() endpoints
│   └── Services
│       └── MpesaService.php           # talks to Safaricom (token, STK push)
├── config
│   └── services.php                   # 'mpesa' settings read from .env
├── routes
│   └── api.php                        # /api/mpesa/pay and /api/mpesa/callback
├── .env.example                       # required variables (no secrets)
└── README.md
```

## Troubleshooting

| Problem | Likely cause and fix |
|---|---|
| `Composer detected issues in your platform` | Installed packages need a newer PHP than you run. Use `composer config platform.php <your version>` and run `composer update`. |
| `Invalid Access Token` or 401 | Wrong Consumer Key or Secret, or the config cache is stale. Run `php artisan config:clear`. |
| `Invalid PhoneNumber` | The number must be in `254XXXXXXXXX` format. |
| `Invalid CallBackURL` | The callback is empty or `localhost`. Use a public HTTPS URL such as an ngrok address. |
| `cURL error 60: SSL certificate problem` | Common on XAMPP. Download `cacert.pem`, set `curl.cainfo` and `openssl.cafile` in `php.ini`, then restart the terminal. |
| `config('services.mpesa.key')` returns `null` | The `.env` variable name does not match `config/services.php`, or the config cache is stale. |
| No callback arrives | Your tunnel is not running, the URL in `.env` is outdated, or the sandbox simply does not send a real callback. |

## Going Live

1. Complete Safaricom's **Go Live** process on the developer portal.
2. Replace the sandbox credentials with your production Consumer Key, Secret, Shortcode and Passkey.
3. Set `MPESA_ENVIRONMENT=live`.
4. Set `MPESA_CALLBACK_URL` to your production HTTPS URL.
5. For a **Till number**, use `CustomerBuyGoodsOnline` as the `TransactionType` and set `PartyB` to the Till number.
6. Run `php artisan config:clear` (or `config:cache`) after deploying.

## Roadmap

- [ ] Save transactions in a `payments` table (migration and model)
- [ ] Match callbacks to payments using `CheckoutRequestID`
- [ ] STK Push query (check payment status)
- [ ] Mark orders as paid on successful callback
- [ ] Validate callback source and make processing idempotent
- [ ] Automated tests with `Http::fake()`
- [ ] C2B, B2C and transaction reversal examples

## Security

- Never commit `.env`. It is listed in `.gitignore`, and only `.env.example` (with empty secrets) belongs in the repository.
- Treat your Consumer Key, Secret and Passkey like passwords. If they are ever exposed, regenerate them on the Daraja portal.
- Do not trust a callback blindly in production. Verify it against your own records before marking anything as paid.

If you find a security issue, please open a private report instead of a public issue.

## Looking for Collaborators

This project is open to collaborators. Whether you are a beginner learning Laravel or an experienced developer who knows M-Pesa well, you are welcome to join in. Good places to start:

- Pick an item from the [Roadmap](#roadmap)
- Improve the documentation or add examples
- Write automated tests
- Add other Daraja APIs (C2B, B2C, reversals)
- Review the code and suggest improvements

To get involved, open an issue to introduce yourself or discuss an idea, or reach out through [GitHub](https://github.com/Dantechdevs) or [dantechdevelopers.com](https://dantechdevelopers.com).

## Contributing

Contributions are welcome.

1. Fork the repository
2. Create a branch: `git checkout -b feat/your-feature`
3. Commit using [Conventional Commits](https://www.conventionalcommits.org/): `git commit -m "feat(scope): description"`
4. Push the branch: `git push origin feat/your-feature`
5. Open a Pull Request

## License

This project is open-sourced under the [MIT License](LICENSE).

## Credits

- **[Safaricom](https://www.safaricom.co.ke)** for the M-Pesa Daraja API and the [Daraja developer portal](https://developer.safaricom.co.ke), which make this integration possible. M-Pesa and Daraja are products and trademarks of Safaricom PLC. This project is an independent community example and is not affiliated with or endorsed by Safaricom.
- **[Laravel](https://laravel.com)** and its community for the framework and documentation.

## Author

**Daniel Ngwasi**, [Dantechdevs Developers](https://dantechdevelopers.com)

- GitHub: [@Dantechdevs](https://github.com/Dantechdevs)
- Portfolio: [ngwasidaniel.vercel.app](https://ngwasidaniel.vercel.app)

If this project helped you, please consider giving it a star.