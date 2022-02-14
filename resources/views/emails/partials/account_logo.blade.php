@if ($account->hasLogo())
    @if ($account->website)
        <a href="{{ $account->website }}" style="color: #19BB40; text-decoration: underline;">
    @endif

    <img src="https://invoice.rgdesign.fr/images/invoiceninja-logo.png" width="118" height="50" style="float:left" alt="RG Design">

    @if ($account->website)
        </a>
    @endif
@endif
