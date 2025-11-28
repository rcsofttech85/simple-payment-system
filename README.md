# Paysera Transfer Feature

This repository contains a **Paysera-style fund transfer feature** built with **Symfony 7**, **PHP 8.4**, **Doctrine ORM**, and **Redis** for caching/idempotency.  
It demonstrates secure, idempotent, and concurrency-safe transfers between accounts.

---

## Features

- **Account Balances**: Each account has a balance tracked in the database.
- **Transfers**: Create a transfer from one account to another.
- **Idempotency**: Repeated requests with the same key return the same result.
- **Concurrency Safety**: Prevents double-spending using **Symfony Lock**.
- **Database Transactions**: Ensures consistency of balances during transfer.
- **Redis Caching**: Stores idempotency responses for performance and safety.

---



## Architecture Diagram

![Paysera Transfer Architecture](docs/paysera.png)
