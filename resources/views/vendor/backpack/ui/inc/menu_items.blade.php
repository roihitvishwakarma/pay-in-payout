{{-- This file is used for menu items by any Backpack v7 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Merchants" icon="la la-store" :link="backpack_url('merchant')" />
<x-backpack::menu-item title="Wallets" icon="la la-wallet" :link="backpack_url('wallet')" />
<x-backpack::menu-item title="Pay-ins" icon="la la-arrow-down" :link="backpack_url('pay-in')" />
<x-backpack::menu-item title="Payouts" icon="la la-arrow-up" :link="backpack_url('payout')" />
