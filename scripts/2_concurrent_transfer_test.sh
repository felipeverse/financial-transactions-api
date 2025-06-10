#!/bin/bash

# Configurações
API_URL="http://api.simplebank.local/transactions/transfer"
PAYER_ID=1
PAYEE_ID=2
TRANSFER_VALUE=1.00
COUNT=50

# Corpo da requisição
BODY="{\"payer_id\":$PAYER_ID,\"payee_id\":$PAYEE_ID,\"value\":$TRANSFER_VALUE}"

echo "Iniciando $COUNT transferências concorrentes do usuário $PAYER_ID para o usuário $PAYEE_ID..."

start_time=$(date +%s%3N) 

for i in $(seq 1 $COUNT); do
  (
    status=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL" \
      -H "Content-Type: application/json" \
      -d "$BODY")

    echo "#$i $PAYER_ID → $PAYEE_ID: Status - $status"
  ) &
done

wait

end_time=$(date +%s%3N)
elapsed=$((end_time - start_time))

echo -e "\nTodos as transferências finalizadas."
echo "Tempo total: ${elapsed}ms"