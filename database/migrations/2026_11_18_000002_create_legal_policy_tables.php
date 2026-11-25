<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_policies', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('legal_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_policy_id')->constrained('legal_policies')->restrictOnDelete();
            $table->string('version');
            $table->longText('content');
            $table->enum('status', ['draft', 'published', 'unpublished']);
            $table->date('effective_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_policy_id', 'version']);
            $table->index(['legal_policy_id', 'status', 'effective_at']);
        });

        Schema::create('legal_policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('legal_policy_id')->constrained('legal_policies')->restrictOnDelete();
            $table->foreignId('legal_policy_version_id')->constrained('legal_policy_versions')->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('accepted_at');

            $table->unique(['booking_id', 'legal_policy_id']);
            $table->index(['customer_id', 'accepted_at']);
        });

        $now = now();
        $draftNotice = 'DRAFT — PENDING BUSINESS AND LEGAL REVIEW. This placeholder is not final legal advice or a confirmed company policy. Replace it with approved, accurate business rules before relying on or representing it as a final policy.';
        $policies = [
            [
                'slug' => 'terms-and-conditions',
                'title' => 'Terms & Conditions',
                'content' => $draftNotice."\n\n## Draft terms\n\nThe booking process, rental agreement, eligibility requirements, payment arrangements, and other terms have not yet been confirmed. Insert the complete terms approved by FleetGuard and its legal adviser. No fees, penalties, or guarantees are established by this placeholder.",
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'content' => $draftNotice."\n\n## Information and purposes\n\nThe information collected, purposes and legal bases for processing, recipients, retention periods, data-subject rights, and contact details must be confirmed and described accurately by FleetGuard before publication as a final notice.\n\n## Draft notice\n\nThis placeholder does not establish or certify compliance with Republic Act No. 10173 (the Data Privacy Act of 2012) or its implementing rules.",
            ],
            [
                'slug' => 'rental-policy',
                'title' => 'Rental Policy',
                'content' => $draftNotice."\n\n## Draft rental information\n\nVehicle eligibility, renter requirements, authorized use, pickup and return procedures, deposits, insurance, and any associated charges have not been confirmed. Insert the approved rental rules before relying on them.",
            ],
            [
                'slug' => 'cancellation-policy',
                'title' => 'Cancellation Policy',
                'content' => $draftNotice."\n\n## Draft cancellation information\n\nCancellation timelines, procedures, eligibility, and any consequences have not been confirmed. Insert the approved cancellation rules before relying on them. This placeholder does not promise a cancellation outcome.",
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Refund Policy',
                'content' => $draftNotice."\n\n## Draft refund information\n\nRefund eligibility, processing, timing, and any applicable conditions have not been confirmed. Insert the approved refund rules before relying on them. This placeholder does not promise a refund.",
            ],
            [
                'slug' => 'late-return-policy',
                'title' => 'Late Return Policy',
                'content' => $draftNotice."\n\n## Draft late-return information\n\nHow late returns are handled, any notification requirements, and any related charges or exceptions have not been confirmed. Insert the approved rules before relying on them. No fee or penalty is established by this placeholder.",
            ],
            [
                'slug' => 'damage-accident-policy',
                'title' => 'Damage & Accident Policy',
                'content' => $draftNotice."\n\n## Draft incident information\n\nReporting steps, responsibilities, insurance arrangements, assessment procedures, and any possible charges have not been confirmed. Insert the approved, accurate procedure before relying on it. No liability or amount is established by this placeholder.",
            ],
        ];

        foreach ($policies as $policy) {
            $policyId = DB::table('legal_policies')->insertGetId([
                'slug' => $policy['slug'],
                'title' => $policy['title'],
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('legal_policy_versions')->insert([
                'legal_policy_id' => $policyId,
                'version' => '1.0-draft',
                'content' => $policy['content'],
                'status' => 'published',
                'effective_at' => null,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_policy_acceptances');
        Schema::dropIfExists('legal_policy_versions');
        Schema::dropIfExists('legal_policies');
    }
};
