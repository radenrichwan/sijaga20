<?php

namespace App\Providers;

use App\Models\AdditionalPoint;
use App\Models\DutyAttendance;
use App\Models\LatenessRecord;
use App\Models\MorningReward;
use App\Models\PointLedger;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use App\Policies\PointLedgerPolicy;
use App\Policies\PointTransactionPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(PointTransaction::class, PointTransactionPolicy::class);
        Gate::policy(PointLedger::class, PointLedgerPolicy::class);
        Gate::define('can_manage_schedule', fn (User $user): bool => $user->canManageSchedule());

        Relation::morphMap([
            'violation' => Violation::class,
            'lateness_record' => LatenessRecord::class,
            'morning_reward' => MorningReward::class,
            'additional_point' => AdditionalPoint::class,
            'duty_attendance' => DutyAttendance::class,
            'point_transaction' => PointTransaction::class,
        ]);
    }
}
