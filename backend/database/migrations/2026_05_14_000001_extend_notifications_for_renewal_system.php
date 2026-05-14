<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'notifiable_type')) {
                $table->string('notifiable_type')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('notifications', 'notifiable_id')) {
                $table->unsignedBigInteger('notifiable_id')->nullable()->after('notifiable_type');
            }

            if (! Schema::hasColumn('notifications', 'category')) {
                $table->enum('category', ['renewal', 'payment', 'alert', 'system'])->default('alert')->after('type');
            }

            if (! Schema::hasColumn('notifications', 'channel')) {
                $table->enum('channel', ['in_app', 'email', 'sms', 'whatsapp'])->default('in_app')->after('category');
            }

            if (! Schema::hasColumn('notifications', 'priority')) {
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->after('channel');
            }

            if (! Schema::hasColumn('notifications', 'dedup_key')) {
                $table->string('dedup_key')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('notifications', 'data')) {
                $table->json('data')->nullable()->after('dedup_key');
            }

            if (! Schema::hasColumn('notifications', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('data');
            }

            $table->unique(['tenant_id', 'dedup_key'], 'notifications_tenant_dedup_unique');
            $table->index(['tenant_id', 'category', 'read'], 'notifications_tenant_category_read');
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_index');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_logs', 'notification_id')) {
                $table->foreignId('notification_id')->nullable()->after('tenant_id')->constrained('notifications')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('notification_logs', 'channel')) {
                $table->enum('channel', ['in_app', 'email', 'sms', 'whatsapp'])->default('in_app')->after('notification_id');
            }

            if (! Schema::hasColumn('notification_logs', 'recipient')) {
                $table->string('recipient')->nullable()->after('channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            foreach (['notification_id'] as $column) {
                if (Schema::hasColumn('notification_logs', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['channel', 'recipient'] as $column) {
                if (Schema::hasColumn('notification_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            try { $table->dropUnique('notifications_tenant_dedup_unique'); } catch (\Throwable) {}
            try { $table->dropIndex('notifications_tenant_category_read'); } catch (\Throwable) {}
            try { $table->dropIndex('notifications_notifiable_index'); } catch (\Throwable) {}

            foreach (['notifiable_type', 'notifiable_id', 'category', 'channel', 'priority', 'dedup_key', 'data', 'expires_at'] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
