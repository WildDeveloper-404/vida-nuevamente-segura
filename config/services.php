<?php
return ['stripe'=>['key'=>env('STRIPE_KEY'),'secret'=>env('STRIPE_SECRET'),'webhook_secret'=>env('STRIPE_WEBHOOK_SECRET'),'currency'=>env('STRIPE_CURRENCY','usd')],'gemini'=>['key'=>env('GEMINI_API_KEY'),'model'=>env('GEMINI_MODEL','gemini-2.5-flash')]];
