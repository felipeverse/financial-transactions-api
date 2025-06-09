<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use App\Models\User;
use App\Enums\UserType;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DepositTest extends TestCase
{
    // 1. Testes de sucesso (happy path)
    public function test_deposit_100_reais_successfully(): void
    {
        $user = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 100.00,
        ]);

        $response->assertOk();
        $response->assertJson([
            'user_id' => $user->id,
            'balance' => '100,00'
        ]);
        $this->assertSame(10000, $user->wallet->fresh()->balance);
    }

    public function test_deposit_minimum_value_successfully()
    {
        $user = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 0.01,
        ]);

        $response->assertOk();
        $response->assertJson([
            'user_id' => $user->id,
            'balance' => '0,01'
        ]);

        $this->assertSame(1, $user->wallet->fresh()->balance);
    }

    public function test_deposit_1000_99_successfully()
    {
        $user = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $response = $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 1000.99,
        ]);

        $response->assertOk();
        $response->assertJson([
            'user_id' => $user->id,
            'balance' => '1.000,99'
        ]);
        $this->assertSame(100099, $user->wallet->fresh()->balance);
    }

    // 2. Testes de validação (FormRequest)
    public function test_payer_id_missing_returns_422()
    {
        $this->postJson(route('api.deposit'), ['value' => 100])
            ->assertStatus(422);
    }

    public function test_value_missing_returns_422()
    {
        $user = User::factory()->create();
        $this->postJson(route('api.deposit'), ['payer_id' => $user->id])
            ->assertStatus(422);
    }

    public function test_value_less_than_or_equal_zero_returns_422()
    {
        $user = User::factory()->create();
        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 0,
        ])->assertStatus(422);
    }

    public function test_value_with_more_than_two_decimals_returns_422()
    {
        $user = User::factory()->create();
        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 100.123,
        ])->assertStatus(422);
    }

    public function test_value_with_invalid_format_returns_422()
    {
        $user = User::factory()->create();

        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 'abc',
        ])->assertStatus(422);

        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => '10,00',
        ])->assertStatus(422);
    }

    // 3. Regras de negócio (camada service)
    public function test_deposit_user_not_found_returns_404()
    {
        $this->postJson(route('api.deposit'), [
            'payer_id' => 999999,
            'value' => 10,
        ])->assertStatus(404);
    }

    public function test_user_without_wallet_returns_500()
    {
        $user = User::factory()->create(['type' => UserType::Common]);
        $user->wallet()->delete();

        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 10,
        ])->assertStatus(500);
    }

    public function test_user_is_not_common_returns_403()
    {
        $user = User::factory()->create(['type' => UserType::Merchant]);

        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 10,
        ])->assertStatus(403);
    }

    // 4. Consistência numérica
    public function test_deposit_with_99_cents_is_precise()
    {
        $user = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 10.99,
        ])->assertOk();

        $this->assertSame(1099, $user->wallet->fresh()->balance);
    }

    public function test_deposit_with_01_cent_is_precise()
    {
        $user = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 0.01,
        ])->assertOk();

        $this->assertSame(1, $user->wallet->fresh()->balance);
    }

    // 5. Exceções inesperadas
    public function test_transaction_create_exception_returns_500()
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                $mock = Mockery::mock('alias:' . Transaction::class);
                $mock->shouldReceive('create')->andThrow(new \Exception('DB error'));

                return $callback();
            });

        $user = User::factory()
            ->withBalance(0)
            ->create(['type' => UserType::Common]);

        $this->postJson(route('api.deposit'), [
            'payer_id' => $user->id,
            'value' => 10.00,
        ])->assertStatus(500);
    }
}
