#!/bin/bash

URL="http://api.simplebank.local/transactions/transfer"
PAYER_ID=1
PAYEE_ID=2
VALOR=1.00
REQS=5

echo "Iniciando $((REQS * 2)) transferências concorrentes cruzadas..."

start_time=$(date +%s%3N) 
for i in $(seq 1 $REQS); do
  (
    status=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$URL" \
      -H "Content-Type: application/json" \
      -d "{\"payer_id\":$PAYER_ID,\"payee_id\":$PAYEE_ID,\"value\":$VALOR}")
    echo "#$i $PAYER_ID → $PAYEE_ID: Status - $status"
  ) &

  (
    status=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$URL" \
      -H "Content-Type: application/json" \
      -d "{\"payer_id\":$PAYEE_ID,\"payee_id\":$PAYER_ID,\"value\":$VALOR}")
    echo "#$i $PAYEE_ID → $PAYER_ID: Status: $status"
  ) &
done
end_time=$(date +%s%3N)
elapsed=$((end_time - start_time))

wait
echo -e "\nTransferências concluídas."
echo "Tempo total: ${elapsed}ms"