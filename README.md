# Blockchain WFH Activity Monitoring

A work-from-home activity monitor where every recorded activity is written to a **Hyperledger Fabric** ledger,
so entries cannot be silently changed afterwards. Built as a group project for a university course (STNCS, June 2026).

## Architecture

```
PHP web client (client-php)  ->  Node.js REST API (rest-api-wfh)  ->  Fabric gateway (gRPC)  ->  JavaScript chaincode (chaincode-wfh)
```

- **chaincode-wfh**: Fabric chaincode (JavaScript, `fabric-contract-api`). Functions include `CreateActivity`, `ReadActivity`,
  `UpdateActivity`, `DeleteActivity`, `GetAllActivities`, `GetActivityHistory`, `GetActivitiesByEmployee`, `GetActivitiesByDate`,
  `GetLastBlock`, `ValidateChain`, `AuthUser` and `CreateUser`. Each activity carries a SHA-256 hash of its content.
- **rest-api-wfh**: Express server that talks to the peer through `@hyperledger/fabric-gateway`. Endpoints: `POST /api/login`,
  `GET/POST /api/activities`, `GET/PUT/DELETE /api/activities/:id`, `GET /api/chain`, `GET /api/validate`,
  `GET /api/employee/:name`, `GET /api/history/:id`, `GET /api/date/:date`, `POST /api/init`.
- **client-php**: the web interface (login, activity list, add activity, admin view of the chain).
- **database**: a MySQL schema (`wfh_monitoring.sql`) kept from an earlier version of the design.

## Running it

This repository does **not** include a Fabric network or any keys. You need:

1. A Hyperledger Fabric `test-network` with a channel named `mychannel` and this chaincode deployed under the name `wfh`.
   `rest-api-wfh/server.js` expects the network's `organizations` folder (Org1, `User1`) next to this project; adjust the paths there.
2. `cd rest-api-wfh && npm install && npm start` (listens on port 3000).
3. Serve `client-php` with PHP (for example XAMPP) and open `login.php`.
4. Call `POST /api/init` once to create the demo accounts.

## Demo accounts

`admin / admin123` and `employee1` to `employee5` / `user123`. These are **demo credentials for local use only**.

## Known limitations

This is a coursework prototype, not a production system:

- Passwords are stored as unsalted SHA-256 hashes inside the chaincode, and the demo passwords are shown on the login page.
  A real system should use a slow salted hash (bcrypt or argon2) and proper identity management (Fabric CA) instead of an app-level user table.
- `ValidateChain` checks the application-level hash chain of activities; it is not a replacement for Fabric's own ledger guarantees.
- The Fabric network, channel and chaincode deployment are not scripted here.
- No automated tests.

All names in the demo data are generic placeholders.
