<?php
return [
 // Deliberately sandbox only. Production credentials and endpoints are not supported in RC 1.00.
 'enabled'=>env('PTEROHUB_SANDBOX_PAYMENTS',false),
 'stripe'=>[
  'secret'=>env('PTEROHUB_STRIPE_TEST_SECRET',''),
  'webhook_secret'=>env('PTEROHUB_STRIPE_TEST_WEBHOOK_SECRET',''),
 ],
 'paypal'=>[
  'client_id'=>env('PTEROHUB_PAYPAL_SANDBOX_CLIENT_ID',''),
  'client_secret'=>env('PTEROHUB_PAYPAL_SANDBOX_CLIENT_SECRET',''),
  'webhook_id'=>env('PTEROHUB_PAYPAL_SANDBOX_WEBHOOK_ID',''),
 ],
];
