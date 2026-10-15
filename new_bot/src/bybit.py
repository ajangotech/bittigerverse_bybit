import os
import time
import requests

BYBIT_API_URL = os.getenv("BYBIT_API_URL", "https://api2.bybit.com")
TOPPER_API_URL = "https://06-bittiger.ajango.com.ng/api/ad-price-limit"

# Static payload for topper price limit (BTC/NGN)
_TOPPER_PAYLOAD = {
    "api_key": "2sFzCpV8cHiZmwTPkW",
    "api_secret": "cdmqIm71yQq1d6jboPW1EcK0vwq1Lq8jO7hQ",
    "id": "2096259035641274368",
    "price": "999999999",
    "priceType": 0,
    "premium": "0",
    "minAmount": "10.000",
    "maxAmount": "100000.000",
    "lastQuantity": "1",
    "paymentPeriod": 30,
    "paymentTerms": [
        {
            "accountNo": "              6119914118",
            "bankName": "", "branchName": "", "businessName": "", "clabe": "",
            "concept": "", "debitCardNumber": "", "firstName": "",
            "id": "2870270", "lastName": "", "mobile": "", "payMessage": "",
            "paymentConfig": {
                "paymentDialect": "payment_field_Opay", "paymentName": "Opay",
                "paymentTemplateItem": [
                    {"fieldName": "realName", "labelDialect": "input_field_realName", "placeholderDialect": "input_tip_realName"},
                    {"fieldName": "accountNo", "labelDialect": "input_field_accountNo", "placeholderDialect": "input_tip_accountNo"},
                    {"fieldName": "branchName", "labelDialect": "input_field_branchName", "placeholderDialect": "input_tip_branchName"},
                    {"fieldName": "bankName", "labelDialect": "input_field_bankName", "placeholderDialect": "input_tip_bankName"},
                ],
                "paymentType": 520,
            },
            "paymentExt1": "", "paymentExt2": "", "paymentExt3": "",
            "paymentExt4": "", "paymentExt5": "", "paymentExt6": "",
            "paymentTemplateVersion": 1, "paymentType": 520, "qrcode": "",
            "realName": "muhsin gambo ", "realNameVerified": True,
            "secondLastName": "", "visible": 1,
        },
        {
            "accountNo": "0436384779",
            "bankName": "", "branchName": "", "businessName": "", "clabe": "",
            "concept": "", "debitCardNumber": "", "firstName": "",
            "id": "2871006", "lastName": "", "mobile": "", "payMessage": "",
            "paymentConfig": {
                "paymentDialect": "payment_field_GTB Bank (Guarantee Trust Bank)",
                "paymentName": "GTB Bank (Guarantee Trust Bank)",
                "paymentTemplateItem": [
                    {"fieldName": "realName", "labelDialect": "input_field_realName", "placeholderDialect": "input_tip_realName"},
                    {"fieldName": "accountNo", "labelDialect": "input_field_accountNo", "placeholderDialect": "input_tip_accountNo"},
                    {"fieldName": "branchName", "labelDialect": "input_field_branchName", "placeholderDialect": "input_tip_branchName"},
                    {"fieldName": "bankName", "labelDialect": "input_field_bankName", "placeholderDialect": "input_tip_bankName"},
                ],
                "paymentType": 516,
            },
            "paymentExt1": "", "paymentExt2": "", "paymentExt3": "",
            "paymentExt4": "", "paymentExt5": "", "paymentExt6": "",
            "paymentTemplateVersion": 1, "paymentType": 516, "qrcode": "",
            "realName": "muhsin gambo", "realNameVerified": True,
            "secondLastName": "", "visible": 1,
        },
        {
            "accountNo": "00",
            "bankName": "", "branchName": "", "businessName": "", "clabe": "",
            "concept": "", "debitCardNumber": "", "firstName": "",
            "id": "2871319", "lastName": "", "mobile": "", "payMessage": "",
            "paymentConfig": {
                "paymentDialect": "payment_field_First Bank", "paymentName": "First Bank",
                "paymentTemplateItem": [
                    {"fieldName": "realName", "labelDialect": "input_field_realName", "placeholderDialect": "input_tip_realName"},
                    {"fieldName": "accountNo", "labelDialect": "input_field_accountNo", "placeholderDialect": "input_tip_accountNo"},
                    {"fieldName": "branchName", "labelDialect": "input_field_branchName", "placeholderDialect": "input_tip_branchName"},
                    {"fieldName": "bankName", "labelDialect": "input_field_bankName", "placeholderDialect": "input_tip_bankName"},
                ],
                "paymentType": 515,
            },
            "paymentExt1": "", "paymentExt2": "", "paymentExt3": "",
            "paymentExt4": "", "paymentExt5": "", "paymentExt6": "",
            "paymentTemplateVersion": 1, "paymentType": 515, "qrcode": "",
            "realName": "muhsin gambo", "realNameVerified": True,
            "secondLastName": "", "visible": 1,
        },
        {
            "accountNo": "6359473806",
            "bankName": "Moniepoint ", "branchName": "Moniepoint ",
            "businessName": "", "clabe": "", "concept": "",
            "debitCardNumber": "", "firstName": "",
            "id": "3852078", "lastName": "", "mobile": "", "payMessage": "",
            "paymentConfig": {
                "paymentDialect": "payment_field_14", "paymentName": "Bank Transfer",
                "paymentTemplateItem": [
                    {"fieldName": "realName", "labelDialect": "input_field_realName", "placeholderDialect": "input_tip_realName"},
                    {"fieldName": "accountNo", "labelDialect": "input_field_accountNo", "placeholderDialect": "input_tip_accountNo"},
                    {"fieldName": "branchName", "labelDialect": "input_field_branchName", "placeholderDialect": "input_tip_branchName"},
                    {"fieldName": "bankName", "labelDialect": "input_field_bankName", "placeholderDialect": "input_tip_bankName"},
                ],
                "paymentType": 14,
            },
            "paymentExt1": "", "paymentExt2": "", "paymentExt3": "",
            "paymentExt4": "", "paymentExt5": "", "paymentExt6": "",
            "paymentTemplateVersion": 1, "paymentType": 14, "qrcode": "",
            "realName": "MUHSIN Gambo", "realNameVerified": True,
            "secondLastName": "", "visible": 1,
        },
    ],
    "tradingPreferenceSet": {
        "completeRateDay30": "0", "hasCompleteRateDay30": 0,
        "hasNationalLimit": 1, "hasOrderFinishNumberDay30": 0,
        "hasRegisterTime": 0, "hasSingleUserOrderLimit": 0,
        "hasUnPostAd": 1, "isEmail": 0, "isKyc": 1, "isMobile": 0,
        "nationalLimit": "NGA", "orderFinishNumberDay30": 0,
        "registerTimeThreshold": 0, "singleUserOrderLimit": 0,
    },
}


def fetch_ads(api_url, api_key, api_secret):
    res = requests.post(
        f"{api_url}/v5/ads/list",
        json={"api_key": api_key, "api_secret": api_secret},
        timeout=30,
    )
    res.raise_for_status()
    data = res.json()
    return data.get("result", {}).get("items", []) or []


def fetch_competitors(api_url, api_key, api_secret, token_id, currency_id, margin_pct=4):
    last_error = None
    for attempt in range(3):
        try:
            res = requests.post(
                f"{api_url}/v5/ads/list",
                json={
                    "api_key": api_key,
                    "api_secret": api_secret,
                    "tokenId": token_id,
                    "currencyId": currency_id,
                    "side": "0",
                    "marginPct": margin_pct,
                },
                timeout=30,
            )
            if not res.ok:
                last_error = f"Analyze market failed: {res.status_code}"
                if attempt < 2:
                    time.sleep(2)
                    continue
                raise Exception(last_error)
            data = res.json()
            if not data.get("status"):
                return {"status": False, "competitors": []}
            return {"status": True, "competitors": data.get("top_10_competitors", []) or []}
        except Exception as e:
            last_error = str(e)
            if attempt < 2:
                time.sleep(2)
                continue
    raise Exception(last_error or "Analyze market failed")


def update_ad_price(api_url, api_key, api_secret, ad, new_price):
    payload = dict(ad)
    payload["price"] = str(new_price)
    payload["api_key"] = api_key
    payload["api_secret"] = api_secret

    last_error = None
    for attempt in range(10):
        try:
            res = requests.post(
                f"{api_url}/v5/ads/update",
                json=payload,
                timeout=30,
            )
            result = res.json()
            if res.ok and not result.get("error"):
                return {"success": True, "price": float(new_price), "error": None}
            last_error = result.get("error") or f"HTTP {res.status_code}"
        except Exception as e:
            last_error = str(e)
        time.sleep(1)
    return {"success": False, "price": None, "error": last_error}


def fetch_topper_price():
    try:
        res = requests.post(
            TOPPER_API_URL,
            json=_TOPPER_PAYLOAD,
            timeout=15,
        )
        if not res.ok:
            return None
        data = res.json()
        if data and data.get("price"):
            return float(data["price"])
    except Exception:
        pass
    return None
