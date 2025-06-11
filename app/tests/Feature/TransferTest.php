<?php

namespace Tests\Feature;

use Exception;
use Tests\TestCase;
use App\Models\User;
use App\Enums\UserType;
use App\Enums\TransactionType;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Event;
use App\Services\TransactionAuthorizerService;
use App\Events\Transactions\TransferProcessedEvent;
use App\DTOs\Services\Responses\TransactionAuthorizer\AuthorizeServiceResponseDTO;

class TransferTest extends TestCase
{
    // 1. Testes de sucesso (happy path)
    public function test_transfer_100_reais_successfully(): void
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(15000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(5000)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 100.00,
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Transfer processed successfully.',
            'transaction' => [
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'type' => 'transfer',
                'amount' => 100,
            ],
            'wallet' => [
                'user_id' => $payer->id,
                'balance' => 50
            ]
        ]);

        $this->assertSame(5000, $payer->wallet->fresh()->balance);
        $this->assertSame(15000, $payee->wallet->fresh()->balance);
    }

    public function test_transfer_minimum_value_successfully()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(100)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 0.01,
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Transfer processed successfully.',
            'transaction' => [
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'type' => 'transfer',
                'amount' => 0.01,
            ],
            'wallet' => [
                'user_id' => $payer->id,
                'balance' => 0.99
            ]
        ]);

        $this->assertSame(99, $payer->wallet->fresh()->balance);
        $this->assertSame(1, $payee->wallet->fresh()->balance);
    }

    public function test_transfer_all_balance_successfully()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(10000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 100.00,
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Transfer processed successfully.',
            'transaction' => [
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'type' => 'transfer',
                'amount' => 100,
            ],
            'wallet' => [
                'user_id' => $payer->id,
                'balance' => 0
            ]
        ]);

        $this->assertSame(0, $payer->wallet->fresh()->balance);
        $this->assertSame(10000, $payee->wallet->fresh()->balance);
    }

    // 2. Testes de validação (FormRequest)
    public function test_payer_id_missing_returns_422()
    {
        $payee = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payee_id' => $payee->id,
            'value' => 100
        ])->assertStatus(422);
    }

    public function test_payee_id_missing_returns_422()
    {
        $payer = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'value' => 100
        ])->assertStatus(422);
    }

    public function test_value_missing_returns_422()
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ])->assertStatus(422);
    }

    public function test_payer_and_payee_same_returns_422()
    {
        $user = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $user->id,
            'payee_id' => $user->id,
            'value' => 100,
        ])->assertStatus(422);
    }

    public function test_value_less_than_or_equal_zero_returns_422()
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 0,
        ])->assertStatus(422);

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => -10,
        ])->assertStatus(422);
    }

    public function test_value_with_more_than_two_decimals_returns_422()
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 100.123,
        ])->assertStatus(422);
    }

    public function test_value_with_invalid_format_returns_422()
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 'abc',
        ])->assertStatus(422);

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => '10,00',
        ])->assertStatus(422);
    }

    public function test_payer_id_not_integer_returns_422()
    {
        $payee = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => 'abc',
            'payee_id' => $payee->id,
            'value' => 100,
        ])->assertStatus(422);
    }

    public function test_payee_id_not_integer_returns_422()
    {
        $payer = User::factory()->create();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => 'abc',
            'value' => 100,
        ])->assertStatus(422);
    }

    // 3. Regras de negócio
    public function test_payer_not_found_returns_404()
    {
        $this->mockAuthorizerService(true);
        $payee = User::factory()->create();
        $nonExistentId = User::max('id') + 1;

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $nonExistentId,
            'payee_id' => $payee->id,
            'value' => 10,
        ])->assertStatus(404);
    }

    public function test_payee_not_found_returns_404()
    {
        $this->mockAuthorizerService(true);
        $payer = User::factory()->create();
        $nonExistentId = User::max('id') + 1;

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $nonExistentId,
            'value' => 10,
        ])->assertStatus(404);
    }

    public function test_payer_user_is_not_common_returns_403()
    {
        $this->mockAuthorizerService(true);
        $payer = User::factory()->create(['type' => UserType::Merchant]);
        $payee = User::factory()->create(['type' => UserType::Common]);

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 10,
        ])->assertStatus(403);
    }

    public function test_payer_wallet_not_found_returns_404()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()->create(['type' => UserType::Common]);
        $payee = User::factory()->create(['type' => UserType::Common]);

        $payer->wallet()->delete();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 10,
        ])->assertStatus(404);
    }

    public function test_payee_wallet_not_found_returns_404()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()->create(['type' => UserType::Common]);
        $payee = User::factory()->create(['type' => UserType::Common]);

        $payee->wallet()->delete();

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 10,
        ])->assertStatus(404);
    }

    public function test_insufficient_balance_returns_422()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(500)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 10.00,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Insufficient balance.']);

        $this->assertSame(500, $payer->wallet->fresh()->balance);
        $this->assertSame(0, $payee->wallet->fresh()->balance);
    }

    public function test_authorization_service_failure_returns_error()
    {
        $this->mockAuthorizerService(false, 'Authorization denied', 403);

        $payer = User::factory()
            ->withBalance(10000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 100.00,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Authorization denied']);

        $this->assertSame(10000, $payer->wallet->fresh()->balance);
        $this->assertSame(0, $payee->wallet->fresh()->balance);
    }

    // 4. Consistência numérica
    public function test_transfer_with_99_cents_is_precise()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(2000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 10.99,
        ])->assertOk();

        $response->assertOk();

        $response->assertJson([
            'message' => 'Transfer processed successfully.',
            'transaction' => [
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'type' => 'transfer',
                'amount' => 10.99,
            ],
            'wallet' => [
                'user_id' => $payer->id,
                'balance' => 9.01
            ]
        ]);

        $this->assertSame(901, $payer->wallet->fresh()->balance);
        $this->assertSame(1099, $payee->wallet->fresh()->balance);
    }

    public function test_transfer_with_01_cent_is_precise()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(100)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 0.01,
        ]);

        $response->assertOk();

        $response->assertJson([
            'message' => 'Transfer processed successfully.',
            'transaction' => [
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'type' => 'transfer',
                'amount' => 0.01,
            ],
            'wallet' => [
                'user_id' => $payer->id,
                'balance' => 0.99
            ]
        ]);

        $this->assertSame(99, $payer->wallet->fresh()->balance);
        $this->assertSame(1, $payee->wallet->fresh()->balance);
    }

    public function test_transfer_with_decimal_precision_100_25()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(15000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 100.25,
        ]);

        $response->assertOk();

        $response->assertJson([
            'message' => 'Transfer processed successfully.',
            'transaction' => [
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'type' => 'transfer',
                'amount' => 100.25,
            ],
            'wallet' => [
                'user_id' => $payer->id,
                'balance' => 49.75
            ]
        ]);

        $this->assertSame(4975, $payer->wallet->fresh()->balance);
        $this->assertSame(10025, $payee->wallet->fresh()->balance);
    }

    // 5. Testes de transação e eventos
    public function test_transfer_creates_transaction_record()
    {
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(10000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 50.00,
        ])->assertOk();

        $this->assertDatabaseHas('transactions', [
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payee->wallet->id,
            'type' => TransactionType::Transfer,
            'amount' => 5000,
        ]);
    }

    public function test_transfer_dispatches_event()
    {
        Event::fake();
        $this->mockAuthorizerService(true);

        $payer = User::factory()
            ->withBalance(10000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 50.00,
        ])->assertOk();

        Event::assertDispatched(TransferProcessedEvent::class);
    }

    // 6. Exceções inesperadas
    public function test_transaction_create_exception_returns_500()
    {
        $this->mockAuthorizerService(true);

        $serviceMock = $this->mock(TransactionService::class);
        $serviceMock->shouldReceive('transfer')->andThrow(new Exception('Service error'));

        $payer = User::factory()
            ->withBalance(10000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 10.00,
        ])->assertStatus(500);
    }

    public function test_unexpected_exception_in_controller_returns_500()
    {
        $this->mock(TransactionAuthorizerService::class, function ($mock) {
            $mock->shouldReceive('authorize')
                ->andThrow(new \Exception('Unexpected error.'));
        });

        $payer = User::factory()
            ->withBalance(10000)
            ->create(['type' => UserType::Common]);

        $payee = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.transactions.transfer'), [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'value' => 10.00,
        ]);

        $response->assertStatus(500);
        $response->assertJson(['error' => 'Unexpected error.']);
    }

    // Métodos auxiliares
    private function mockAuthorizerService(bool $success, string $message = 'Authorized', int $statusCode = 200)
    {
        $response = new AuthorizeServiceResponseDTO(
            $success,
            $message,
            null,
            statusCode: $statusCode
        );

        $this->mock(TransactionAuthorizerService::class, function ($mock) use ($response) {
            $mock->shouldReceive('authorize')->andReturn($response);
        });
    }
}
