<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Dsn;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $publicUrl = (string) config('app.url');
        if (parse_url($publicUrl, PHP_URL_SCHEME) === 'https') {
            URL::useOrigin($publicUrl);
            URL::forceScheme('https');
        }

        Mail::extend('brevo', function (array $config) {
            $apiKey = trim((string) $config['key']);
            if ($apiKey === '') {
                throw new TransportException('Falta configurar BREVO_API_KEY.');
            }

            $client = HttpClient::create(['max_duration' => 15, 'max_redirects' => 0]);

            return (new BrevoTransportFactory(client: $client))
                ->create(new Dsn('brevo+api', 'default', $apiKey));
        });
    }
}
