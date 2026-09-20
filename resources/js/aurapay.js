import { Passkeys } from '@laravel/passkeys';


function csrfToken() {
    return document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute('content');
}


async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',

        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers || {}),
        },

        ...options,
    });

    const data = await response
        .json()
        .catch(() => null);

    if (!response.ok) {
        throw new Error(
            data?.message ||
            `Request failed (${response.status})`
        );
    }

    return data;
}


// ============================================================
// PASSKEY LOGIN
// ============================================================

const passkeyLoginButton =
    document.getElementById('passkey-login');

if (passkeyLoginButton) {
    passkeyLoginButton.addEventListener(
        'click',
        async () => {
            try {
                await Passkeys.verify();

                window.location.href =
                    '/dashboard';

            } catch (error) {
                console.warn(
                    'Passkey sign-in was cancelled or failed',
                    error
                );

                alert(
                    'Passkey sign-in did not complete. ' +
                    'You can also log in with your password below.'
                );
            }
        }
    );
}


// ============================================================
// PASSKEY REGISTRATION
// ============================================================

const passkeyRegisterButton =
    document.getElementById('passkey-register');

if (passkeyRegisterButton) {
    passkeyRegisterButton.addEventListener(
        'click',
        async () => {
            const name = prompt(
                'Name this device (e.g. "My laptop"):',
                'My device'
            );

            if (!name) {
                return;
            }

            try {
                await Passkeys.register({
                    name,
                });

                alert(
                    'Passkey registered. ' +
                    'Log out and sign back in with it to try it.'
                );

            } catch (error) {
                console.warn(
                    'Passkey registration was cancelled or failed',
                    error
                );
            }
        }
    );
}


// ============================================================
// DASHBOARD
// ============================================================

const dashboard =
    document.getElementById('aurapay-dashboard');

if (dashboard) {

    const walletId =
        dashboard.dataset.walletId;


    /*
     * Token generated after successful VoP.
     *
     * It represents confirmation of:
     * - authenticated user
     * - receiver wallet
     * - amount
     * - expiration time
     *
     * Any change in payment parameters invalidates it.
     */
    let a2aConfirmationToken = null;


    // ========================================================
    // WALLET
    // ========================================================

    async function refreshWallet() {
        const wallet =
            await apiFetch(
                `/api/wallets/${walletId}`
            );

        const balance =
            document.getElementById(
                'wallet-balance'
            );

        if (balance) {
            balance.textContent =
                `${wallet.balance} ${wallet.currency}`;
        }
    }


    // ========================================================
    // TRANSACTIONS
    // ========================================================

    async function refreshTransactions() {
        const page =
            await apiFetch(
                '/api/transactions'
            );

        const tbody =
            document.getElementById(
                'transactions-body'
            );

        if (!tbody) {
            return;
        }

        tbody.innerHTML = '';

        (page.data || [])
            .forEach((tx) => {

                const row =
                    document.createElement('tr');

                row.innerHTML = `
                    <td>${tx.reference.slice(0, 8)}</td>
                    <td>${tx.type}</td>
                    <td>${tx.amount}</td>
                    <td>${tx.status}</td>
                    <td>${tx.risk_level}</td>
                `;

                tbody.appendChild(row);
            });
    }


    // ========================================================
    // CONSENTS
    // ========================================================

    async function refreshConsents() {
        const consents =
            await apiFetch(
                '/api/consents'
            );

        const list =
            document.getElementById(
                'consents-list'
            );

        if (!list) {
            return;
        }

        list.innerHTML = '';

        consents.forEach((consent) => {

            const li =
                document.createElement('li');

            li.textContent =
                `${consent.scope} — ${consent.status}`;

            list.appendChild(li);
        });
    }


    // ========================================================
    // TEST WALLET TOP-UP
    // ========================================================

    document
        .getElementById('top-up-form')
        ?.addEventListener(
            'submit',
            async (event) => {

                event.preventDefault();

                try {
                    await apiFetch(
                        `/api/wallets/${walletId}/top-up`,
                        {
                            method: 'POST',

                            body: JSON.stringify({
                                amount:
                                    event.target
                                        .amount
                                        .value,
                            }),
                        }
                    );

                    event.target.reset();

                    await refreshWallet();

                } catch (error) {
                    alert(error.message);
                }
            }
        );


    // ========================================================
    // GRANT CONSENT
    // ========================================================

    document
        .querySelectorAll(
            '.grant-consent'
        )
        .forEach((button) => {

            button.addEventListener(
                'click',
                async () => {

                    try {
                        await apiFetch(
                            '/api/consents',
                            {
                                method: 'POST',

                                body:
                                    JSON.stringify({
                                        scope:
                                            button
                                                .dataset
                                                .scope,
                                    }),
                            }
                        );

                        await refreshConsents();

                    } catch (error) {
                        alert(error.message);
                    }
                }
            );
        });


    // ========================================================
    // VERIFICATION OF PAYEE + PAYMENT CONFIRMATION
    // ========================================================

    document
        .getElementById('vop-check')
        ?.addEventListener(
            'click',
            async () => {

                const form =
                    document.getElementById(
                        'a2a-form'
                    );

                const result =
                    document.getElementById(
                        'vop-result'
                    );

                if (!form || !result) {
                    return;
                }


                /*
                 * Never reuse a token from a previous
                 * verification.
                 */
                a2aConfirmationToken = null;


                try {
                    const response =
                        await apiFetch(
                            '/api/vop/check',
                            {
                                method: 'POST',

                                body:
                                    JSON.stringify({

                                        receiver_wallet_id:
                                            form
                                                .receiver_wallet_id
                                                .value,

                                        receiver_name:
                                            form
                                                .receiver_name
                                                .value,

                                        amount:
                                            form
                                                .amount
                                                .value,
                                    }),
                            }
                        );


                    /*
                     * A token is stored only for an
                     * exact VoP match.
                     */
                    if (
                        response.status ===
                        'match'
                    ) {

                        if (
                            !response
                                .confirmation_token
                        ) {
                            throw new Error(
                                'The server did not return a payment confirmation token.'
                            );
                        }

                        a2aConfirmationToken =
                            response
                                .confirmation_token;

                        result.textContent =
                            `${response.status}: ` +
                            `${response.message} — ` +
                            `payment details confirmed`;

                    } else {

                        a2aConfirmationToken =
                            null;

                        result.textContent =
                            `${response.status}: ` +
                            `${response.message}`;
                    }

                } catch (error) {

                    a2aConfirmationToken =
                        null;

                    result.textContent =
                        error.message;
                }
            }
        );


    // ========================================================
    // INVALIDATE CONFIRMATION IF PAYMENT DATA CHANGES
    // ========================================================

    const a2aForm =
        document.getElementById(
            'a2a-form'
        );

    if (a2aForm) {

        /*
         * These are the fields bound to the payment
         * confirmation token.
         */
        const protectedFields =
            a2aForm.querySelectorAll(
                [
                    '[name="receiver_wallet_id"]',
                    '[name="receiver_name"]',
                    '[name="amount"]',
                ].join(',')
            );


        const invalidateConfirmation =
            () => {

                if (
                    a2aConfirmationToken ===
                    null
                ) {
                    return;
                }

                a2aConfirmationToken =
                    null;

                const result =
                    document.getElementById(
                        'vop-result'
                    );

                if (result) {
                    result.textContent =
                        'Payment details changed. ' +
                        'Please verify the recipient again.';
                }
            };


        protectedFields.forEach(
            (field) => {

                field.addEventListener(
                    'input',
                    invalidateConfirmation
                );

                field.addEventListener(
                    'change',
                    invalidateConfirmation
                );
            }
        );
    }


    // ========================================================
    // A2A PAYMENT
    // ========================================================

    document
        .getElementById('a2a-form')
        ?.addEventListener(
            'submit',
            async (event) => {

                event.preventDefault();


                /*
                 * STEP 4.5:
                 *
                 * An A2A payment cannot be submitted
                 * without a valid confirmation token.
                 */
                if (!a2aConfirmationToken) {

                    alert(
                        'Please verify the recipient ' +
                        'and confirm the payment details ' +
                        'before sending the payment.'
                    );

                    return;
                }


                /*
                 * One key is generated for this logical
                 * payment attempt.
                 */
                const idempotencyKey =
                    crypto.randomUUID();
const correlationId =
    crypto.randomUUID();

                try {
                    await apiFetch(
                        '/api/transactions/a2a',
                        {
                            method: 'POST',

                            headers: {
    'Idempotency-Key':
        idempotencyKey,

    'X-Correlation-ID':
        correlationId,
},

                            body:
                                JSON.stringify({

                                    receiver_wallet_id:
                                        event
                                            .target
                                            .receiver_wallet_id
                                            .value,

                                    receiver_name:
                                        event
                                            .target
                                            .receiver_name
                                            .value,

                                    amount:
                                        event
                                            .target
                                            .amount
                                            .value,

                                    /*
                                     * STEP 4.5:
                                     * Server receives the
                                     * cryptographically protected
                                     * confirmation token.
                                     */
                                    confirmation_token:
                                        a2aConfirmationToken,
                                }),
                        }
                    );


                    /*
                     * Successful payment means the token
                     * must never be reusable.
                     */
                    a2aConfirmationToken =
                        null;


                    event.target.reset();


                    const result =
                        document.getElementById(
                            'vop-result'
                        );

                    if (result) {
                        result.textContent =
                            'Payment submitted successfully.';
                    }


                    await refreshWallet();
                    await refreshTransactions();

                } catch (error) {

                    /*
                     * If the server rejects the token,
                     * it should not be reused.
                     *
                     * Requiring a fresh verification is
                     * safer than keeping stale confirmation.
                     */
                    a2aConfirmationToken =
                        null;


                    const result =
                        document.getElementById(
                            'vop-result'
                        );

                    if (result) {
                        result.textContent =
                            'Payment was not submitted. ' +
                            'Please verify the recipient again.';
                    }


                    alert(error.message);
                }
            }
        );


    // ========================================================
    // CREATE QR PAYMENT REQUEST
    // ========================================================

    document
        .getElementById(
            'qr-create-form'
        )
        ?.addEventListener(
            'submit',
            async (event) => {

                event.preventDefault();

                try {
                    const paymentRequest =
                        await apiFetch(
                            '/api/qr',
                            {
                                method: 'POST',

                                body:
                                    JSON.stringify({

                                        amount:
                                            event
                                                .target
                                                .amount
                                                .value
                                            || null,

                                        note:
                                            event
                                                .target
                                                .note
                                                .value
                                            || null,
                                    }),
                            }
                        );


                    const referenceOutput =
                        document.getElementById(
                            'qr-reference-output'
                        );

                    const signatureOutput =
                        document.getElementById(
                            'qr-signature-output'
                        );


                    if (referenceOutput) {
                        referenceOutput
                            .textContent =
                            paymentRequest.reference;
                    }


                    if (signatureOutput) {
                        signatureOutput
                            .textContent =
                            paymentRequest.signature;
                    }

                } catch (error) {
                    alert(error.message);
                }
            }
        );


    // ========================================================
    // PAY QR PAYMENT REQUEST
    // ========================================================

    document
        .getElementById(
            'qr-pay-form'
        )
        ?.addEventListener(
            'submit',
            async (event) => {

                event.preventDefault();

                try {
                    await apiFetch(
                        `/api/qr/${event.target.reference.value}/pay`,
                        {
                            method: 'POST',

                            body:
                                JSON.stringify({

                                    signature:
                                        event
                                            .target
                                            .signature
                                            .value,

                                    amount:
                                        event
                                            .target
                                            .amount
                                            .value
                                        || null,
                                }),
                        }
                    );


                    event.target.reset();

                    await refreshWallet();
                    await refreshTransactions();

                } catch (error) {
                    alert(error.message);
                }
            }
        );


    // ========================================================
    // INITIAL DASHBOARD LOAD
    // ========================================================

    /*
     * account_info consent is now required for
     * wallet and transaction-information endpoints.
     *
     * Therefore a fresh user without that consent may
     * receive 403 responses here until consent is granted.
     *
     * We catch the initial errors so one failed refresh
     * does not stop the remainder of the JavaScript file.
     */

    refreshWallet()
        .catch((error) => {
            console.warn(
                'Wallet information is not available:',
                error
            );
        });


    refreshTransactions()
        .catch((error) => {
            console.warn(
                'Transaction information is not available:',
                error
            );
        });


    refreshConsents()
        .catch((error) => {
            console.warn(
                'Consent information could not be loaded:',
                error
            );
        });
}