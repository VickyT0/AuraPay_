<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\PaymentConfirmationService;
use Illuminate\Support\Str;
use App\Models\Transaction;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;


    private function createUserWithWallet(
        string $name,
        string $email,
        float $balance = 500
    ): array {

        $user =
            User::factory()->create([
                'name' => $name,
                'email' => $email,
            ]);

        $wallet =
            Wallet::create([
                'user_id' => $user->id,
                'balance' => $balance,
                'currency' => 'EUR',
                'status' => 'active',
            ]);

        return [
            $user,
            $wallet,
        ];
    }


    private function grantPaymentConsent(
        User $user
    ): void {

        Consent::create([
            'user_id' => $user->id,
            'scope' => 'payment_initiation',
            'status' => 'granted',
            'granted_at' => now(),
            'expires_at'
                => now()->addDay(),
        ]);
    }


    public function test_user_cannot_read_another_users_wallet(): void
{
    [$userA] =
        $this->createUserWithWallet(
            'User A',
            'a@example.test'
        );

    [, $walletB] =
        $this->createUserWithWallet(
            'User B',
            'b@example.test'
        );

    Consent::create([
        'user_id' => $userA->id,
        'scope' => 'account_info',
        'status' => 'granted',
        'granted_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this
        ->actingAs($userA)
        ->getJson(
            "/api/wallets/{$walletB->id}"
        )
        ->assertForbidden();
}


    public function test_payment_requires_consent(): void
    {
        [$sender] =
            $this->createUserWithWallet(
                'Sender',
                'sender@example.test'
            );

        [$receiver, $receiverWallet] =
            $this->createUserWithWallet(
                'Receiver',
                'receiver@example.test'
            );

        $this
            ->actingAs($sender)
            ->withHeader(
                'Idempotency-Key',
                'missing-consent-test'
            )
            ->postJson(
                '/api/transactions/a2a',
                [
                    'receiver_wallet_id'
                        => $receiverWallet->id,

                    'receiver_name'
                        => $receiver->name,

                    'amount'
                        => 10,
                ]
            )
            ->assertForbidden();
    }


   public function test_idempotency_prevents_duplicate_payment(): void
{
    /*
     * Create sender with enough balance
     * for the test payment.
     */
    [$sender, $senderWallet] =
        $this->createUserWithWallet(
            'Sender',
            'sender-idempotency@example.test',
            500
        );

    /*
     * Create receiver.
     */
    [$receiver, $receiverWallet] =
        $this->createUserWithWallet(
            'Receiver',
            'receiver-idempotency@example.test',
            100
        );

    /*
     * Payment initiation consent is required
     * before an A2A transfer can be executed.
     */
    $this->grantPaymentConsent(
        $sender
    );

    /*
     * Generate the payment confirmation token.
     *
     * IMPORTANT:
     * The amount here MUST be identical to
     * the amount used in the payment payload.
     */
    $confirmationToken =
        app(
            PaymentConfirmationService::class
        )->create(
            $sender,
            $receiverWallet,
            25.00
        );

    /*
     * This represents one logical payment.
     */
    $payload = [
        'receiver_wallet_id'
            => $receiverWallet->id,

        'receiver_name'
            => $receiver->name,

        'amount'
            => 25.00,

        'confirmation_token'
            => $confirmationToken,
    ];

    /*
     * Use exactly the same idempotency key
     * for both requests.
     */
    $idempotencyKey =
        'idempotency-test-key-001';
        $correlationId =
    (string) Str::uuid();

    /*
     * First request:
     * a new transaction must be created.
     */
    $first =
        $this
            ->actingAs($sender)
            ->withHeaders([
    'Idempotency-Key'
        => $idempotencyKey,

    'X-Correlation-ID'
        => $correlationId,
])
            ->postJson(
                '/api/transactions/a2a',
                $payload
            );

    $first->assertCreated();

    /*
     * Second request:
     * same sender + same key + same payment.
     *
     * No second transaction must be created.
     */
    $second =
        $this
            ->actingAs($sender)
           ->withHeaders([
    'Idempotency-Key'
        => $idempotencyKey,

    'X-Correlation-ID'
        => $correlationId,
])
            ->postJson(
                '/api/transactions/a2a',
                $payload
            );

    $second
        ->assertOk()
        ->assertHeader(
            'X-Idempotent-Replay',
            'true'
        );

    /*
     * Only one transaction must exist.
     */
    $this->assertDatabaseCount(
        'transactions',
        1
    );

    /*
     * Sender started with 500.00.
     * Only one 25.00 payment must be deducted.
     *
     * Expected balance:
     * 500.00 - 25.00 = 475.00
     */
    $senderWallet->refresh();

    $this->assertEquals(
        '475.00',
        number_format(
            (float) $senderWallet->balance,
            2,
            '.',
            ''
        )
    );
}


    public function test_vop_exact_match(): void
{
    [$payer] =
        $this->createUserWithWallet(
            'Payer',
            'payer@example.test'
        );

    [$receiver, $wallet] =
        $this->createUserWithWallet(
            'Demo Receiver',
            'vop@example.test'
        );

    $this
        ->actingAs($payer)
        ->postJson(
            '/api/vop/check',
            [
                'receiver_wallet_id'
                    => $wallet->id,

                'receiver_name'
                    => $receiver->name,

                'amount'
                    => 25.00,
            ]
        )
        ->assertOk()
        ->assertJson([
            'status' => 'match',
        ])
        ->assertJsonStructure([
            'confirmation_token',
            'confirmed_amount',
            'confirmed_receiver',
        ]);
}


    public function test_invalid_qr_signature_is_rejected(): void
    {
        [$receiver] =
            $this->createUserWithWallet(
                'QR Receiver',
                'qrreceiver@example.test'
            );

        $this->grantPaymentConsent(
            $receiver
        );

        $create =
            $this
                ->actingAs($receiver)
                ->postJson(
                    '/api/qr',
                    [
                        'amount' => 10,
                    ]
                );

        $create->assertCreated();

        $reference =
            $create->json(
                'reference'
            );

        [$payer] =
            $this->createUserWithWallet(
                'QR Payer',
                'qrpayer@example.test'
            );

        $this->grantPaymentConsent(
            $payer
        );

        $this
            ->actingAs($payer)
            ->postJson(
                "/api/qr/{$reference}/pay",
                [
                    'signature'
                        => str_repeat(
                            'a',
                            64
                        ),
                ]
            )
            ->assertForbidden();
    }


    public function test_non_admin_cannot_read_audit_log(): void
    {
        [$user] =
            $this->createUserWithWallet(
                'Normal User',
                'normal@example.test'
            );

        $this
            ->actingAs($user)
            ->getJson(
                '/api/admin/audit-logs'
            )
            ->assertForbidden();
    }


    public function test_admin_can_read_audit_log(): void
    {
        [$admin] =
            $this->createUserWithWallet(
                'Admin',
                'admin@example.test'
            );

        $admin->forceFill([
            'role' => 'admin',
        ])->save();

        $this
            ->actingAs($admin)
            ->getJson(
                '/api/admin/audit-logs'
            )
            ->assertOk();
    }


    public function test_rate_limit_is_enforced(): void
    {
        [$user] =
            $this->createUserWithWallet(
                'Rate User',
                'rate@example.test'
            );

        $response = null;

        for (
            $i = 1;
            $i <= 61;
            $i++
        ) {

            $response =
                $this
                    ->actingAs($user)
                    ->getJson(
                        '/api/transactions'
                    );
        }

        $response->assertStatus(
            429
        );
    }
    public function test_account_information_requires_consent(): void
{
    [$user, $wallet] =
        $this->createUserWithWallet(
            'Account User',
            'account-info@example.test'
        );

    $this
        ->actingAs($user)
        ->getJson(
            "/api/wallets/{$wallet->id}"
        )
        ->assertForbidden();

    Consent::create([
        'user_id' => $user->id,
        'scope' => 'account_info',
        'status' => 'granted',
        'granted_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this
        ->actingAs($user)
        ->getJson(
            "/api/wallets/{$wallet->id}"
        )
        ->assertOk();
}
public function test_expired_account_information_consent_is_rejected(): void
{
    [$user, $wallet] =
        $this->createUserWithWallet(
            'Expired Consent User',
            'expired-consent@example.test'
        );

    Consent::create([
        'user_id' => $user->id,
        'scope' => 'account_info',
        'status' => 'granted',
        'granted_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);

    $this
        ->actingAs($user)
        ->getJson(
            "/api/wallets/{$wallet->id}"
        )
        ->assertForbidden();
}
public function test_confirmed_payment_parameters_cannot_be_changed(): void
{
    [$sender] =
        $this->createUserWithWallet(
            'Sender',
            'dynamic-link@example.test',
            500
        );

    [$receiver, $receiverWallet] =
        $this->createUserWithWallet(
            'Receiver',
            'dynamic-receiver@example.test',
            100
        );

    $this->grantPaymentConsent(
        $sender
    );

    $token =
        app(
            PaymentConfirmationService::class
        )->create(
            $sender,
            $receiverWallet,
            25.00
        );

        $correlationId =
    (string) Str::uuid();

    $this
        ->actingAs($sender)
        ->withHeaders([
    'Idempotency-Key'
        => 'dynamic-link-test',

    'X-Correlation-ID'
        => $correlationId,
])
        ->postJson(
            '/api/transactions/a2a',
            [
                'receiver_wallet_id'
                    => $receiverWallet->id,

                'receiver_name'
                    => $receiver->name,

                'amount'
                    => 30.00,

                'confirmation_token'
                    => $token,
            ]
        )
        ->assertStatus(422);

    $this->assertDatabaseCount(
        'transactions',
        0
    );
}
public function test_failed_payment_creates_persistent_audit_event(): void
{
    /*
     * Sender has only 5 EUR.
     */
    [$sender] =
        $this->createUserWithWallet(
            'Poor Sender',
            'poor@example.test',
            5
        );

    [$receiver, $receiverWallet] =
        $this->createUserWithWallet(
            'Receiver',
            'audit-receiver@example.test',
            100
        );

    $this->grantPaymentConsent(
        $sender
    );

    /*
     * The user confirms a payment for 50 EUR.
     * Confirmation itself is valid.
     */
    $token =
        app(
            PaymentConfirmationService::class
        )->create(
            $sender,
            $receiverWallet,
            50.00
        );

    $correlationId =
        (string) Str::uuid();

    /*
     * The payment must fail because the sender
     * has insufficient funds, not because of
     * validation or missing headers.
     */
    $this
        ->actingAs($sender)
        ->withHeaders([
            'Idempotency-Key'
                => 'failed-audit-test',

            'X-Correlation-ID'
                => $correlationId,
        ])
        ->postJson(
            '/api/transactions/a2a',
            [
                'receiver_wallet_id'
                    => $receiverWallet->id,

                'receiver_name'
                    => $receiver->name,

                'amount'
                    => 50.00,

                'confirmation_token'
                    => $token,
            ]
        )
        ->assertStatus(422);

    /*
     * Even though the payment failed,
     * the audit event must remain persistent.
     */
    $this->assertDatabaseHas(
        'audit_logs',
        [
            'user_id'
                => $sender->id,

            'action'
                => 'transaction.failed',
        ]
    );
}

public function test_user_is_logged_out_after_five_minutes_of_inactivity(): void
{
    [$user] =
        $this->createUserWithWallet(
            'Idle Timeout User',
            'idle-timeout@example.test'
        );

    /*
     * Simulate an authenticated session
     * whose last activity was 6 minutes ago.
     *
     * This is beyond the allowed
     * five-minute inactivity period.
     */
    $response =
        $this
            ->actingAs($user)
            ->withSession([
                'aurapay_last_activity'
                    => now()
                        ->subMinutes(6)
                        ->timestamp,
            ])
            ->getJson(
                '/api/consents'
            );

    /*
     * Server must terminate the session.
     */
    $response
        ->assertStatus(401)
        ->assertJson([
            'message' =>
                'Session expired after 5 minutes of inactivity.',

            'code' =>
                'SESSION_IDLE_TIMEOUT',
        ]);

    /*
     * Authentication must actually
     * have been removed.
     */
    $this->assertGuest();
}

public function test_session_remains_active_before_five_minute_timeout(): void
{
    [$user] =
        $this->createUserWithWallet(
            'Active Session User',
            'active-session@example.test'
        );

    /*
     * Last activity was only four minutes ago.
     * Session must remain active.
     */
    $response =
        $this
            ->actingAs($user)
            ->withSession([
                'aurapay_last_activity'
                    => now()
                        ->subMinutes(4)
                        ->timestamp,
            ])
            ->getJson(
                '/api/consents'
            );

    $response->assertOk();

    $this->assertAuthenticatedAs(
        $user
    );
}

public function test_session_expires_at_exactly_five_minutes_of_inactivity(): void
{
    [$user] =
        $this->createUserWithWallet(
            'Boundary Timeout User',
            'boundary-timeout@example.test'
        );

    $this
        ->actingAs($user)
        ->withSession([
            'aurapay_last_activity'
                => now()
                    ->subMinutes(5)
                    ->timestamp,
        ])
        ->getJson(
            '/api/consents'
        )
        ->assertStatus(401)
        ->assertJson([
            'code' =>
                'SESSION_IDLE_TIMEOUT',
        ]);

    $this->assertGuest();
}

public function test_high_risk_payment_does_not_transfer_wallet_balance(): void
{
    /*
     * Sender has sufficient balance.
     * Therefore a blocked transfer must be caused
     * by risk controls, not insufficient funds.
     */
    [$sender, $senderWallet] =
        $this->createUserWithWallet(
            'High Risk Sender',
            'high-risk-sender@example.test',
            5000
        );

    [$receiver, $receiverWallet] =
        $this->createUserWithWallet(
            'High Risk Receiver',
            'high-risk-receiver@example.test',
            100
        );

    $this->grantPaymentConsent(
        $sender
    );

    /*
     * Create five recent completed transactions
     * with a low average amount.
     *
     * This makes the next payment trigger:
     *
     * +30 high amount
     * +25 amount > 3x recent average
     * +25 transaction velocity
     *
     * Total >= 60 => HIGH risk.
     */
    for ($i = 1; $i <= 5; $i++) {

        Transaction::create([
            'reference'
                => (string) Str::uuid(),

            'sender_wallet_id'
                => $senderWallet->id,

            'receiver_wallet_id'
                => $receiverWallet->id,

            'amount'
                => 100.00,

            'type'
                => 'a2a',

            'status'
                => 'completed',

            'risk_score'
                => 0,

            'risk_level'
                => 'low',

            'metadata'
                => [
                    'fixture'
                        => 'risk-test-history',
                ],
        ]);
    }

    /*
     * Confirmation is valid for the exact
     * high-risk payment parameters.
     */
    $confirmationToken =
        app(
            PaymentConfirmationService::class
        )->create(
            $sender,
            $receiverWallet,
            1500.00
        );

    $correlationId =
        (string) Str::uuid();

    /*
     * Attempt high-risk A2A transfer.
     */
    $response =
        $this
            ->actingAs($sender)
            ->withHeaders([
                'Idempotency-Key'
                    => 'high-risk-transfer-test',

                'X-Correlation-ID'
                    => $correlationId,
            ])
            ->postJson(
                '/api/transactions/a2a',
                [
                    'receiver_wallet_id'
                        => $receiverWallet->id,

                    'receiver_name'
                        => $receiver->name,

                    'amount'
                        => 1500.00,

                    'confirmation_token'
                        => $confirmationToken,
                ]
            );

    /*
     * High-risk operation is accepted for
     * review, not financially executed.
     */
    $response
        ->assertStatus(202)
        ->assertJson([
            'status'
                => 'flagged',

            'risk_level'
                => 'high',
        ]);

    /*
     * Reload balances from the database.
     */
    $senderWallet->refresh();
    $receiverWallet->refresh();

    /*
     * No financial transfer may have occurred.
     */
    $this->assertSame(
        '5000.00',
        number_format(
            (float) $senderWallet->balance,
            2,
            '.',
            ''
        )
    );

    $this->assertSame(
        '100.00',
        number_format(
            (float) $receiverWallet->balance,
            2,
            '.',
            ''
        )
    );

    /*
     * Transaction exists only as a flagged
     * record for review.
     */
    $this->assertDatabaseHas(
        'transactions',
        [
            'sender_wallet_id'
                => $senderWallet->id,

            'receiver_wallet_id'
                => $receiverWallet->id,

            'status'
                => 'flagged',

            'risk_level'
                => 'high',
        ]
    );
}

}