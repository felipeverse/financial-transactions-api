# API de transações financeiras (financial-transactions-api)

## Descrição

**Sistema backend de transações financeiras com foco em concorrência, escalabilidade e alta disponibilidade.**
Permite depósitos e transferências entre usuários comuns e lojistas, com validações externas, notificações assíncronas, controle transacional rigoroso e suporte à idempotência.

---

## Tecnologias Utilizadas

-   **Laravel** – Framework PHP para APIs RESTful robustas
-   **Docker** – Containerização e orquestração dos serviços
-   **Nginx** – Proxy reverso e balanceador de carga (least_conn)
-   **MySQL** – Banco relacional com suporte a transações ACID
-   **Redis** – Cache e filas de jobs em memória
-   **Laravel Horizon** – Monitoramento de filas e processamento assíncrono

---

## Arquitetura

### Visão Geral

![image.png](./docs/images/image.png)

-   **Load balancer (Nginx)** - Distribui as requisições entre as múltiplas instâncias
-   **Múltiplas instâncias de aplicação** - 2+ instâncias Laravel rodando simultaneamente
-   **Banco de dados MySQL** - Controle de concorrência com Pessimistic Lock
-   **Cache e filas com Redis** - Cache de consultas frequentes e armazenamento de filas de jobs
-   **Sistema de filas (Laravel Horizon)** - Processamento assíncrono de notificações e logs

### Modelagem de dados no banco

![image.png](./docs/images/image%201.png)

### Tratamento de Deadlocks

![image.png](./docs/images/image%202.png)

O sistema implementa ordenação determinística dos locks para evitar deadlocks. Sempre faz lock dos recursos na mesma ordem, independente da direção da transação.

Exemplo: Para transferências entre Wallet 1 e Wallet 2, sempre faz lock primeiro da wallet com menor ID. Isso garante que apenas uma transação por vez tenha acesso aos recursos, eliminando completamente a de deadlock.

---

## Funcionalidades Implementadas

### Depósito

-   Camadas organizadas: Controller → DTO → Service
-   Manipulação de valores em centavos (inteiros) para evitar erros com ponto flutuante
-   Suporte a lock pessimista (via feature flag)
-   Scripts de simulação para concorrência

### Transferência

-   Validação de saldo e autorização via mock externo (`/authorize`)
-   Manipulação monetária segura (inteiros) com conversão automática
-   Transação ACID com rollback em inconsistências
-   Lock pessimista por ordenação determinística
-   Idempotência ativa para evitar duplicidade em retries
-   Suporte a testes de concorrência e deadlock com scripts dedicados

### Notificações asíncronas

-   Envio de POST para mock externo (`/notify`)
-   Executadas em background com retry/backoff automático
-   Lógica desacoplada do core da aplicação

### Logging assíncrono

-   Middleware dedicado captura requests/responses
-   Logs processados via filas, sem impacto na latência da API

---

## Documentação da API

### POST api/transactions/deposit

Realiza depósito na carteira do usuário

**Request**

```json
{
    "payer_id": 1,
    "value": 10.5
}
```

**Response**

```json
{
    "message": "Deposit processed successfully.",
    "transaction": {
        "transaction_id": 14,
        "payer_id": 1,
        "payee_id": 1,
        "type": "deposit",
        "amount": 10.5,
        "created_at": "2025-06-11T08:52:25.000000Z"
    },
    "wallet": {
        "user_id": 1,
        "balance": 10.5
    }
}
```

### POST api/transactions/transfer

Realiza transferências entre usuários

**Request**

```json
{
    "payer_id": 1,
    "payee_id": 2,
    "value": 0.5
}
```

**Response**

```json
{
    "message": "Transfer processed successfully.",
    "transaction": {
        "transaction_id": 15,
        "payer_id": 1,
        "payee_id": 2,
        "type": "transfer",
        "amount": 0.5,
        "created_at": "2025-06-11T08:59:04.000000Z"
    },
    "wallet": {
        "user_id": 1,
        "balance": 99.5
    }
}
```

---

## Testes

-   **Cobertura:** Unitários, integração e concorrência
-   **Scripts incluídos:**
    -   `1_concurrent_deposit_test.sh`
    -   `2_concurrent_transfer_test.sh`
    -   `3_concurrent_transfer_deadlocking_test.sh`
    -   `4_mass_simulate-idempotent-requests.sh`

---

## Qualidade de Código

-   **Pint (PSR-12):** Lint e fix automático
-   **PHPStan (Larastan):** Análise estática de nível elevado
-   **PHP Mess Detector:** Detecção de _code smells_ e complexidade excessiva
-   **Hooks de pré-commit:** Executam Pint automaticamente

### Comandos úteis

```bash
# Testes
docker compose exec app1 php artisan test
docker compose exec app1 php artisan test --coverage

# Lint e fix
docker compose exec app1 composer run lint
docker compose exec app1 composer run fix

# Análise estática e code smells
docker compose exec app1 composer run stan
docker compose exec app1 composer run md
```

## Documentação e Monitoramento

-   Swagger UI: [`localhost/api/documentation`](http://localhost/api/documentation)
-   Horizon (monitoramento de filas): [`localhost/horizon`](http://localhost/horizon)
-   Visualizador de logs: [`localhost/log-viewer`](http://localhost/log-viewer)

```bash
# Gerar documentação Swagger
docker compose exec app1 composer run swagger

# Publicar visualizador de logs
docker compose exec app1 composer run log-viewer

```

---

## Instalação

### Pré-requisitos

-   Docker e Docker Compose
-   Git

### Passos

```bash
git clone https://github.com/seu-usuario/simplebank.git
cd simplebank

# Iniciar containers
docker compose up -d

# (Opcional) acompanhar logs
docker compose logs -f

```

---

## Melhorias Futuras

-   Implementação da camada de repositórios com cache
-   Autenticação e controle de acesso
-   Rate limiting por usuário
-   Agendamento de transferências
-   Transações assíncronas com consulta por UUID
-   Integração com stack de observabilidade (ex: ELK)
-   Testes de carga automatizados com ferramentas especializadas

---

Projeto desenvolvido como prova de conceito de uma aplicação financeira simplificada, com foco em boas práticas de backend, confiabilidade transacional e observabilidade.
