<?php
namespace Tests\Feature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AudioTranscripcionTest extends TestCase
{
    private function audio(): UploadedFile
    {
        $pcm=str_repeat("\0",1600);
        return UploadedFile::fake()->createWithContent('consulta.wav','RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,1,8000,16000,2,16).'data'.pack('V',strlen($pcm)).$pcm);
    }
    public function test_transcribe_para_los_tres_roles_sin_generar_reporte_ni_guardar_audio(): void
    {
        config(['services.gemini.key'=>'ficticia']);Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['candidates'=>[['content'=>['parts'=>[['text'=>'{"texto":"Citas canceladas de este mes"}']]]]]])]);
        foreach(['Administrador','Recepcionista','Medico'] as $rol) {
            $this->withSession($this->sessionFor($this->user($rol)))->post('/reportes/voz/transcribir',['audio'=>$this->audio()],['Accept'=>'application/json'])
                ->assertOk()->assertJsonPath('texto','Citas canceladas de este mes');
        }
        Http::assertSent(fn($request)=>isset($request['contents'][0]['parts'][1]['inlineData']['data']) && $request['contents'][0]['parts'][1]['inlineData']['mimeType']==='audio/wav');
        $this->assertFalse(session()->has('voice_report'));
    }
    public function test_rechaza_archivo_invalido_y_paciente_sin_contactar_proveedor(): void
    {
        Http::preventStrayRequests();Http::fake();
        $this->withSession($this->sessionFor($this->user()))->post('/reportes/voz/transcribir',[
            'audio'=>UploadedFile::fake()->createWithContent('falso.webm','contenido que no es audio')],['Accept'=>'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('audio');
        $this->withSession($this->sessionFor($this->user('Paciente')))->post('/reportes/voz/transcribir',['audio'=>$this->audio()],['Accept'=>'application/json'])->assertForbidden();
        Http::assertNothingSent();
    }
    public function test_audio_silencioso_o_error_del_proveedor_conserva_fallo_controlado(): void
    {
        config(['services.gemini.key'=>'ficticia']);
        $this->withSession($this->sessionFor($this->user()));
        foreach([Http::response(['candidates'=>[['content'=>['parts'=>[['text'=>'{"texto":""}']]]]]]),Http::response([],503)] as $response) {
            Http::fake(['*'=>$response]);
            $this->post('/reportes/voz/transcribir',['audio'=>$this->audio()],['Accept'=>'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('audio');
        }
    }
}
