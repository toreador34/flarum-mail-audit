<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('mail_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('message_id', 190)->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('mailer', 50)->nullable();
            $table->text('subject')->nullable();
            $table->text('from_json')->nullable();
            $table->text('to_json')->nullable();
            $table->text('cc_json')->nullable();
            $table->text('bcc_json')->nullable();
            $table->text('reply_to_json')->nullable();
            $table->longText('headers')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->text('attachments')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('attachment_count')->default(0);
            $table->boolean('has_html')->default(false);
            $table->boolean('has_text')->default(false);
            $table->text('error')->nullable();
            $table->dateTime('created_at')->index();
            $table->dateTime('sent_at')->nullable();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('mail_audit_logs');
    },
];
