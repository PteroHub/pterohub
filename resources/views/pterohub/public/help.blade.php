@extends('pterohub.client.layout')
@section('title','Help center')
@section('description','Information about PteroHub hosting plans, accounts, draft orders and support.')
@section('content')
<header class="ph-client-heading ph-help-heading"><span class="ph-eyebrow">HELP CENTER</span><h1>How can we help?</h1><p>Find answers about hosting plans, account management and the ordering process.</p></header>
<div class="ph-help-layout">
<section class="ph-help-content" aria-label="Frequently asked questions">
  <h2>Frequently asked questions</h2>
  <div class="ph-help-questions">
    <details><summary>How do I choose a game server?</summary><p>Open the game hosting catalog, select a game and compare the available plans. Each product page lists its resources and billing periods.</p><a href="{{ route('ph.client.catalog') }}">Browse game hosting</a></details>
    <details><summary>Can I review my order before submitting it?</summary><p>Yes. Add configurations to your shopping cart and review the quantities, billing periods and total before submitting a draft order.</p><a href="{{ route('ph.cart.show') }}">View shopping cart</a></details>
    <details><summary>Are payments and automatic provisioning available?</summary><p>Not yet. This is a development preview. Orders are saved as drafts, no payment is collected and no game server is automatically created.</p></details>
    <details><summary>Where can I find my orders, invoices and services?</summary><p>Sign in to the client area. Your dashboard provides access to your own records and account details.</p><a href="{{ route('ph.client.login') }}">Client sign in</a></details>
    <details><summary>How do I contact support?</summary><p>Sign in to your client account and open a support ticket. You can review replies and ticket status from the client dashboard.</p><a href="{{ route('ph.client.tickets.create') }}">Open a support ticket</a></details>
    <details><summary>How can I change my account password?</summary><p>After signing in, open Account security from your client workspace. You will need your current password to set a new one.</p></details>
  </div>
</section>
<aside class="ph-help-aside"><div class="ph-client-card"><span class="ph-eyebrow">NEED MORE HELP?</span><h2>Contact support</h2><p>Use the client ticket area to ask a question or follow up on an existing request.</p><a class="ph-client-button" href="{{ route('ph.client.tickets.create') }}">Create support ticket</a></div><div class="ph-client-card"><span class="ph-eyebrow">HOSTING</span><h2>Explore plans</h2><p>Find game server configurations and review the available billing options.</p><a href="{{ route('ph.client.catalog') }}">Open hosting catalog</a></div></aside>
</div>
@endsection
