<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('user_configs', function (Blueprint $collection) {
        $collection->foreignId('user_id')
                    ->constrained()
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
        $collection->string('app_theme')->nullable();
        $collection->timestamps();

      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

      Schema::table('user_configs', function (Blueprint $collection) {
        $collection->dropForeign(['user_id']);
        $collection->dropColumn(['app_theme']);
      });

      // Schema::dropIfExists('user_configs');
    }
};
