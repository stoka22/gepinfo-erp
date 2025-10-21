<?php

// database/migrations/2025_10_20_110000_split_employee_user_links.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) user_id -> created_by_user_id (NULL-ozható maradhat)
        DB::statement('ALTER TABLE `employees` CHANGE `user_id` `created_by_user_id` BIGINT UNSIGNED NULL');

        // 2) account_user_id oszlop
        Schema::table('employees', function (Blueprint $t) {
            $t->foreignId('account_user_id')->nullable()->after('created_by_user_id');
        });

        // 3) FK-k és egyedi index
        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `employees_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
        DB::statement('ALTER TABLE `employees` ADD CONSTRAINT `employees_account_user_id_foreign` FOREIGN KEY (`account_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
        DB::statement('CREATE UNIQUE INDEX `employees_account_user_id_unique` ON `employees`(`account_user_id`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `employees_account_user_id_foreign`');
        DB::statement('ALTER TABLE `employees` DROP FOREIGN KEY `employees_created_by_user_id_foreign`');
        DB::statement('DROP INDEX `employees_account_user_id_unique` ON `employees`');

        Schema::table('employees', function (Blueprint $t) {
            $t->dropColumn('account_user_id');
        });

        DB::statement('ALTER TABLE `employees` CHANGE `created_by_user_id` `user_id` BIGINT UNSIGNED NULL');
    }
};
