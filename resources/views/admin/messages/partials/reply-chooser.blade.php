@php
    $replySubject = 'Re: '.$message->subject.' ('.$message->reference.')';
    $replyBody = 'Hello,'.PHP_EOL.PHP_EOL.'Following up on reference '.$message->reference.'.';
    $replyMenuId = 'reply-menu-'.$message->id.'-'.$instance;
    $replyToggleId = 'reply-toggle-'.$message->id.'-'.$instance;
@endphp

<div class="admin-reply-chooser" data-reply-chooser data-email="{{ $message->email }}" data-subject="{{ $replySubject }}" data-body="{{ $replyBody }}" data-client-label="{{ $label }}">
    <div class="admin-reply-split">
        <a class="admin-message-primary-action admin-reply-main" href="mailto:{{ rawurlencode($message->email) }}?subject={{ rawurlencode($replySubject) }}&amp;body={{ rawurlencode($replyBody) }}" target="_self" rel="noopener noreferrer" data-reply-main>
            <i class="bi bi-envelope" aria-hidden="true"></i>
            <span data-reply-main-label>{{ $label }}</span>
            <small data-reply-current-label>Default mail app</small>
        </a>
        <button class="admin-reply-menu-toggle" id="{{ $replyToggleId }}" type="button" aria-label="Choose reply email client" aria-haspopup="menu" aria-expanded="false" aria-controls="{{ $replyMenuId }}" data-reply-menu-toggle>
            <i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>
    </div>
    <div class="admin-reply-menu" id="{{ $replyMenuId }}" role="menu" aria-labelledby="{{ $replyToggleId }}" hidden data-reply-menu>
        <p>Choose where to compose your reply.</p>
        <button type="button" role="menuitemradio" aria-checked="false" data-reply-choice="mailto">Default mail app</button>
        <button type="button" role="menuitemradio" aria-checked="false" data-reply-choice="gmail">Gmail</button>
        <button type="button" role="menuitemradio" aria-checked="false" data-reply-choice="outlook">Outlook</button>
        <button type="button" role="menuitemradio" aria-checked="false" data-reply-choice="microsoft365">Microsoft 365</button>
        <button type="button" role="menuitemradio" aria-checked="false" data-reply-choice="yahoo">Yahoo Mail</button>
        <button type="button" role="menuitem" data-reply-copy><i class="bi bi-copy" aria-hidden="true"></i> Copy email address</button>
        <label><input type="checkbox" checked data-reply-remember> Remember my choice</label>
        <small>“Default mail app” is controlled by this computer’s own settings.</small>
    </div>
    <span class="admin-reply-toast" role="status" aria-live="polite" hidden data-reply-toast></span>
</div>
