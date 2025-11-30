#  Funds Transfer Feature

This repository contains a **funds transfer feature** built with **Symfony 7**, **PHP 8.4**, **Doctrine ORM**, and **Redis** for caching and idempotency.

It demonstrates **secure, idempotent, and concurrency-safe transfers** between accounts.

---

##  Features

* **Account Balances**: Each account's balance is accurately tracked in the database.
* **Transfers**: Securely create a transfer from a source account to a destination account.
* **Idempotency**: Repeated requests with the same idempotency key return the same, correct result, preventing accidental duplicate transfers.
* **Concurrency Safety**: Prevents race conditions and double-spending using **Symfony Lock** component.
* **Database Transactions**: Ensures atomicity and consistency of account balances during the transfer process.
* **Redis Caching**: Utilized to store idempotency responses for enhanced performance and safety checks.

---

##  Architecture Diagram

![Fund Transfer Architecture](docs/diagram.png)

---

##  Set up

Follow these steps to get the project running locally using Docker.

1.  **Clone the Repository**:
    ```bash
    git clone git@github.com:rcsofttech85/simple-payment-system.git
    ```
2.  **Change Directory**:
    ```bash
    cd simple-payment-system
    ```
3.  **Container Set Up**: Start the Docker containers (PHP, MYSQL, Redis).
    ```bash
    docker compose up -d
    ```
4.  **Install Dependencies**: Execute Composer within the PHP container.
    ```bash
    docker exec -it paysera_php bash
    run composer install
    ```
5.  **Database Set up** (Create database, run migrations, load fixtures):
    ```bash
    bin/console doctrine:database:create --if-not-exists
    ```
    ```bash
    bin/console doctrine:migration:migrate
    ```
    ```bash
    bin/console hautelook:fixtures:load --no-interaction
    ```
6.  **Generate JWT Key Pair**:
    ```bash
    bin/console lexik:jwt:generate-keypair
    ```

### Test Database Setup and Running Tests

To set up the test database and run the unit/functional tests, execute these commands:

```bash
APP_ENV=test bin/console doctrine:database:create --if-not-exists
```
```bash
APP_ENV=test   bin/console doctrine:migration:migrate
 ```
 ```bash
APP_ENV=test   bin/console hautelook:fixtures:load --no-interaction
```
```bash
APP_ENV=test bin/phpunit tests/
 ```

 ### Architecture Choice Explanation

Although this assignment is implemented using REST with API Platform for simplicity, in a real-world environment, I would choose a microservice + event-driven architecture for the following reasons:

**Loose coupling**: Each service can operate independently without direct synchronous dependencies.

**Reliability**: Message brokers (RabbitMQ) guarantee no lost transactions, automatic retries, and durable event logs — critical for financial systems.

**Scalability**: Horizontal scaling becomes significantly easier by adding more event consumers without impacting other services.

**Auditability**: Event streams provide a complete, immutable trace of all financial operations, required for reconciliation and compliance.

**Fault tolerance**: Transfers continue even if downstream services are temporarily unavailable.

### Prompts Used

REST vs event-driven for fintech transfers?
How should transfer and balance updates be handled internally? etc