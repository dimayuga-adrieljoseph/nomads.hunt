#!/bin/bash
# Temporary end-to-end HTTP verification of the two-stage claim lifecycle.
set -e
API=http://127.0.0.1:8123/api
STAMP=$(date +%s)
A_EMAIL="qa_a_${STAMP}@nh.test"
B_EMAIL="qa_b_${STAMP}@nh.test"

j() { python3 -c "import sys,json; d=json.load(sys.stdin); print(json.dumps(d, indent=None)); print(str(eval('d'+sys.argv[1]))) if len(sys.argv)>1 else None" "$1" 2>/dev/null || cat; }
field() { python3 -c "import sys,json;d=json.load(sys.stdin);
p='$1'.split('.');v=d
for k in p:
    v = v[int(k)] if k.isdigit() else v.get(k)
    if v is None: break
print(v)"; }

ADMIN=$(curl -s -X POST $API/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"admin@nomads.hunt","password":"password"}' | field token)

token_for() {
  curl -s -X POST $API/auth/register -H 'Content-Type: application/json' \
    -d "{\"name\":\"$2\",\"email\":\"$1\",\"password\":\"password\",\"password_confirmation\":\"password\"}" | field token
}

# Create a fresh product via admin API
PID=$(curl -s -X POST $API/admin/products -H 'Content-Type: application/json' -H "Authorization: Bearer $ADMIN" \
  -d '{"name":"QA Two-Stage Jacket","category":"Jacket","brand":"QA","size":"L","condition":"good","mine_price":5000,"steal_price":5300,"grab_price":5600}' | field data.id)
echo "product #$PID created"

TA=$(token_for "$A_EMAIL" "QA User A")
TB=$(token_for "$B_EMAIL" "QA User B")

echo "--- 1. A MINE ---"
curl -s -X POST $API/products/$PID/mine -H "Authorization: Bearer $TA" | field message
curl -s -X POST $API/products/$PID/mine -H "Authorization: Bearer $TB" | field message

echo "--- 2. state after mines ---"
curl -s $API/products/$PID | python3 -c "import sys,json;d=json.load(sys.stdin)['data'];print('status=',d['status'],'phase=',d['active_claim']['phase'],'claim_exp=',d['active_claim']['claim_expires_at'],'pay_exp=',d['active_claim']['payment_expires_at'])"

echo "--- 3. A tries to pay during CLAIM stage (must be refused) ---"
A_ORDER=$(curl -s $API/my-claims -H "Authorization: Bearer $TA" | python3 -c "import sys,json;d=json.load(sys.stdin);print(d['active'][0]['order']['id'] if d['active'] and d['active'][0].get('order') else 'NONE')")
echo "A order during claim stage: $A_ORDER"

echo "--- 4. admin force-expire A's claim stage ---"
CLAIM_A=$(curl -s $API/products/$PID | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['active_claim']['id'])")
curl -s -X POST $API/admin/claims/$CLAIM_A/force-expire -H "Authorization: Bearer $ADMIN" | field message

echo "--- 5. state: A should now be in PAYMENT, B must still wait ---"
curl -s $API/products/$PID | python3 -c "import sys,json;d=json.load(sys.stdin)['data'];print('status=',d['status'],'phase=',d['active_claim']['phase'],'pay_exp=',d['active_claim']['payment_expires_at'])"
curl -s $API/my-claims -H "Authorization: Bearer $TB" | python3 -c "import sys,json;d=json.load(sys.stdin);print('B waiting count=',len(d['waiting']),'B active count=',len(d['active']))"

echo "--- 6. A pays during PAYMENT window ---"
A_PAY=$(curl -s $API/my-claims -H "Authorization: Bearer $TA" | python3 -c "import sys,json;d=json.load(sys.stdin);c=d['active'][0];print(c['can_pay'], c['phase'], c['order']['id'])")
echo "A: can_pay/phase/order = $A_PAY"
A_ORDER_ID=$(echo $A_PAY | awk '{print $3}')
curl -s -X POST $API/orders/$A_ORDER_ID/pay -H "Authorization: Bearer $TA" | field message

echo "--- 7. final state ---"
curl -s $API/products/$PID | python3 -c "import sys,json;d=json.load(sys.stdin)['data'];print('status=',d['status'],'active_claim=',d['active_claim'])"
curl -s $API/my-claims -H "Authorization: Bearer $TB" | python3 -c "import sys,json;d=json.load(sys.stdin);print('B waiting=',len(d['waiting']),'B expired=',len(d['expired']),'B completed=',len(d['completed']))"