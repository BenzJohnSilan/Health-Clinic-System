<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('doctor_schedule_exceptions', function (Blueprint $table) {
            $table->id();

            // 👨‍⚕️ Doctor this exception belongs to
            $table->foreignId('doctor_id')
                ->constrained('users')
                ->onDelete('cascade');

            // 📅 The specific calendar date this exception applies to
            $table->date('exception_date');

            // 📌 Unavailable (leave/holiday) or Custom Hours (special schedule)
            $table->enum('type', ['Unavailable', 'Custom Hours']);

            // 🕒 Only used when type = Custom Hours
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // 📝 Optional reason (e.g. "Leave", "Holiday")
            $table->string('reason', 500)->nullable();

            $table->timestamps();

            // 🔐 No duplicate exception dates per doctor
            $table->unique(['doctor_id', 'exception_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('doctor_schedule_exceptions');
    }
};
