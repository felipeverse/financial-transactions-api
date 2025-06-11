#!/bin/bash

# Configurações
API_URL="http://api.simplebank.local/transactions/deposit"
PAYER_ID=1
DEPOSIT_VALUE=1.00
COUNT=10
IDEMPOTENCY_KEY="test-key-$(uuidgen)"

# Corpo da requisição
BODY="{\"payer_id\":$PAYER_ID,\"value\":$DEPOSIT_VALUE}"

echo "Enviando $COUNT requisições com a mesma Idempotency-Key: $IDEMPOTENCY_KEY"

start_time=$(date +%s%3N)

for i in $(seq 1 $COUNT); do
  (
    response=$(curl -s -D - -o /dev/null -X POST "$API_URL" \
      -H "Content-Type: application/json" \
      -H "Idempotency-Key: $IDEMPOTENCY_KEY" \
      -d "$BODY")

    status=$(echo "$response" | grep HTTP | tail -1 | awk '{print $2}')
    relayed=$(echo "$response" | grep -i "idempotency-relayed")

    if [[ -n "$relayed" ]]; then
      echo "#$i - Status: $status (relayed)"
    else
      echo "#$i - Status: $status (processed)"
    fi
  ) &
done

wait

end_time=$(date +%s%3N)
elapsed=$((end_time - start_time))

echo -e "\nTodos os depósitos finalizados."
echo "Tempo total: ${elapsed}ms"