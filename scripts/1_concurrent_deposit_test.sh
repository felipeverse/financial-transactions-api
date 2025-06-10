#!/bin/bash

# Configurações
API_URL="http://api.simplebank.local/transactions/deposit"
PAYER_ID=1
DEPOSIT_VALUE=1.00
COUNT=100

# Corpo da requisição
BODY="{\"payer_id\":$PAYER_ID,\"value\":$DEPOSIT_VALUE}"

echo "Iniciando $COUNT depósitos concorrentes no valor de $DEPOSIT_VALUE para o usuário $PAYER_ID..."

start_time=$(date +%s%3N) 

for i in $(seq 1 $COUNT); do
  (
    status=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL" \
      -H "Content-Type: application/json" \
      -d "$BODY")

    echo "#$i - Status: $status"
  ) &
done

wait

end_time=$(date +%s%3N)
elapsed=$((end_time - start_time))

echo -e "\nTodos os depósitos finalizados."
echo "Tempo total: ${elapsed}ms"
EXPECTED_BALANCE=$(echo "$COUNT * $DEPOSIT_VALUE" | bc)
echo "Saldo esperado: +$EXPECTED_BALANCE"