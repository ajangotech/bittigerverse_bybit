/*
 * Bybit P2P API wrapper
 * Handles: fetching ads, analyzing market (competitors), updating ad price, and fetching topper price limit.
 */

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Fetch all advertisements for a user.
 * @param {string} apiUrl - base API URL
 * @param {string} apiKey
 * @param {string} apiSecret
 * @returns {Promise<Array>} list of ad items
 */
async function fetchAds(apiUrl, apiKey, apiSecret) {
  const res = await fetch(`${apiUrl}/ads`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ api_key: apiKey, api_secret: apiSecret }),
  });
  if (!res.ok) throw new Error(`Fetch ads failed: ${res.status}`);
  const data = await res.json();
  return data?.result?.items || [];
}

/**
 * Fetch competitors for a token/currency pair.
 * @param {string} apiUrl
 * @param {string} apiKey
 * @param {string} apiSecret
 * @param {string} tokenId
 * @param {string} currencyId
 * @param {number} marginPct
 * @returns {Promise<{status: boolean, competitors: Array}>}
 */
async function fetchCompetitors(apiUrl, apiKey, apiSecret, tokenId, currencyId, marginPct = 4) {
  let lastError = null;
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const res = await fetch(`${apiUrl}/analyze-market`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          api_key: apiKey,
          api_secret: apiSecret,
          tokenId,
          currencyId,
          side: '0',
          marginPct,
        }),
      });
      if (!res.ok) {
        lastError = `Analyze market failed: ${res.status}`;
        if (attempt < 2) {
          await sleep(2000);
          continue;
        }
        throw new Error(lastError);
      }
      const data = await res.json();
      if (!data.status) return { status: false, competitors: [] };
      return { status: true, competitors: data.top_10_competitors || [] };
    } catch (e) {
      lastError = e.message;
      if (attempt < 2) {
        await sleep(2000);
        continue;
      }
    }
  }
  throw new Error(lastError || 'Analyze market failed');
}

/**
 * Update an ad's price via the API. Auto-retries on failure every 1 second.
 * @param {string} apiUrl
 * @param {string} apiKey
 * @param {string} apiSecret
 * @param {object} ad - full ad object
 * @param {string|number} newPrice
 * @returns {Promise<{success: boolean, price: number|null, error: string|null}>}
 */
async function updateAdPrice(apiUrl, apiKey, apiSecret, ad, newPrice) {
  const payload = {
    ...ad,
    price: String(newPrice),
    api_key: apiKey,
    api_secret: apiSecret,
  };

  let lastError = null;
  for (let attempt = 0; attempt < 10; attempt++) {
    try {
      const res = await fetch(`${apiUrl}/update-ad`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const result = await res.json();

      if (res.ok && !result.error) {
        return { success: true, price: parseFloat(newPrice), error: null };
      }
      lastError = result?.error || `HTTP ${res.status}`;
    } catch (e) {
      lastError = e.message;
    }
    await sleep(1000);
  }
  return { success: false, price: null, error: lastError };
}

/**
 * Fetch the topper price limit from the external API.
 * Uses the same hardcoded static payload as the web app (BTC NGN).
 * @returns {Promise<number|null>} topper price or null
 */
async function fetchTopperPrice() {
  try {
    const payload = {
      api_key: '2sFzCpV8cHiZmwTPkW',
      api_secret: 'cdmqIm71yQq1d6jboPW1EcK0vwq1Lq8jO7hQ',
      id: '2096259035641274368',
      price: '999999999',
      priceType: 0,
      premium: '0',
      minAmount: '10.000',
      maxAmount: '100000.000',
      lastQuantity: '1',
      paymentPeriod: 30,
      paymentTerms: [
        {
          accountNo: '              6119914118',
          bankName: '',
          branchName: '',
          businessName: '',
          clabe: '',
          concept: '',
          debitCardNumber: '',
          firstName: '',
          id: '2870270',
          lastName: '',
          mobile: '',
          payMessage: '',
          paymentConfig: {
            paymentDialect: 'payment_field_Opay',
            paymentName: 'Opay',
            paymentTemplateItem: [
              { fieldName: 'realName', labelDialect: 'input_field_realName', placeholderDialect: 'input_tip_realName' },
              { fieldName: 'accountNo', labelDialect: 'input_field_accountNo', placeholderDialect: 'input_tip_accountNo' },
              { fieldName: 'branchName', labelDialect: 'input_field_branchName', placeholderDialect: 'input_tip_branchName' },
              { fieldName: 'bankName', labelDialect: 'input_field_bankName', placeholderDialect: 'input_tip_bankName' },
            ],
            paymentType: 520,
          },
          paymentExt1: '',
          paymentExt2: '',
          paymentExt3: '',
          paymentExt4: '',
          paymentExt5: '',
          paymentExt6: '',
          paymentTemplateVersion: 1,
          paymentType: 520,
          qrcode: '',
          realName: 'muhsin gambo ',
          realNameVerified: true,
          secondLastName: '',
          visible: 1,
        },
        {
          accountNo: '0436384779',
          bankName: '',
          branchName: '',
          businessName: '',
          clabe: '',
          concept: '',
          debitCardNumber: '',
          firstName: '',
          id: '2871006',
          lastName: '',
          mobile: '',
          payMessage: '',
          paymentConfig: {
            paymentDialect: 'payment_field_GTB Bank (Guarantee Trust Bank)',
            paymentName: 'GTB Bank (Guarantee Trust Bank)',
            paymentTemplateItem: [
              { fieldName: 'realName', labelDialect: 'input_field_realName', placeholderDialect: 'input_tip_realName' },
              { fieldName: 'accountNo', labelDialect: 'input_field_accountNo', placeholderDialect: 'input_tip_accountNo' },
              { fieldName: 'branchName', labelDialect: 'input_field_branchName', placeholderDialect: 'input_tip_branchName' },
              { fieldName: 'bankName', labelDialect: 'input_field_bankName', placeholderDialect: 'input_tip_bankName' },
            ],
            paymentType: 516,
          },
          paymentExt1: '',
          paymentExt2: '',
          paymentExt3: '',
          paymentExt4: '',
          paymentExt5: '',
          paymentExt6: '',
          paymentTemplateVersion: 1,
          paymentType: 516,
          qrcode: '',
          realName: 'muhsin gambo',
          realNameVerified: true,
          secondLastName: '',
          visible: 1,
        },
        {
          accountNo: '00',
          bankName: '',
          branchName: '',
          businessName: '',
          clabe: '',
          concept: '',
          debitCardNumber: '',
          firstName: '',
          id: '2871319',
          lastName: '',
          mobile: '',
          payMessage: '',
          paymentConfig: {
            paymentDialect: 'payment_field_First Bank',
            paymentName: 'First Bank',
            paymentTemplateItem: [
              { fieldName: 'realName', labelDialect: 'input_field_realName', placeholderDialect: 'input_tip_realName' },
              { fieldName: 'accountNo', labelDialect: 'input_field_accountNo', placeholderDialect: 'input_tip_accountNo' },
              { fieldName: 'branchName', labelDialect: 'input_field_branchName', placeholderDialect: 'input_tip_branchName' },
              { fieldName: 'bankName', labelDialect: 'input_field_bankName', placeholderDialect: 'input_tip_bankName' },
            ],
            paymentType: 515,
          },
          paymentExt1: '',
          paymentExt2: '',
          paymentExt3: '',
          paymentExt4: '',
          paymentExt5: '',
          paymentExt6: '',
          paymentTemplateVersion: 1,
          paymentType: 515,
          qrcode: '',
          realName: 'muhsin gambo',
          realNameVerified: true,
          secondLastName: '',
          visible: 1,
        },
        {
          accountNo: '6359473806',
          bankName: 'Moniepoint ',
          branchName: 'Moniepoint ',
          businessName: '',
          clabe: '',
          concept: '',
          debitCardNumber: '',
          firstName: '',
          id: '3852078',
          lastName: '',
          mobile: '',
          payMessage: '',
          paymentConfig: {
            paymentDialect: 'payment_field_14',
            paymentName: 'Bank Transfer',
            paymentTemplateItem: [
              { fieldName: 'realName', labelDialect: 'input_field_realName', placeholderDialect: 'input_tip_realName' },
              { fieldName: 'accountNo', labelDialect: 'input_field_accountNo', placeholderDialect: 'input_tip_accountNo' },
              { fieldName: 'branchName', labelDialect: 'input_field_branchName', placeholderDialect: 'input_tip_branchName' },
              { fieldName: 'bankName', labelDialect: 'input_field_bankName', placeholderDialect: 'input_tip_bankName' },
            ],
            paymentType: 14,
          },
          paymentExt1: '',
          paymentExt2: '',
          paymentExt3: '',
          paymentExt4: '',
          paymentExt5: '',
          paymentExt6: '',
          paymentTemplateVersion: 1,
          paymentType: 14,
          qrcode: '',
          realName: 'MUHSIN Gambo',
          realNameVerified: true,
          secondLastName: '',
          visible: 1,
        },
      ],
      tradingPreferenceSet: {
        completeRateDay30: '0',
        hasCompleteRateDay30: 0,
        hasNationalLimit: 1,
        hasOrderFinishNumberDay30: 0,
        hasRegisterTime: 0,
        hasSingleUserOrderLimit: 0,
        hasUnPostAd: 1,
        isEmail: 0,
        isKyc: 1,
        isMobile: 0,
        nationalLimit: 'NGA',
        orderFinishNumberDay30: 0,
        registerTimeThreshold: 0,
        singleUserOrderLimit: 0,
      },
    };

    const res = await fetch('https://06-bittiger.ajango.com.ng/api/ad-price-limit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!res.ok) return null;
    const data = await res.json();
    if (data && data.price) {
      return parseFloat(data.price);
    }
  } catch (e) {
    // silent fail — topper price is best-effort
  }
  return null;
}

module.exports = { fetchAds, fetchCompetitors, updateAdPrice, fetchTopperPrice, sleep };
