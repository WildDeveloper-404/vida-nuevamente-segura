const {test}=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const source=readFileSync(require('node:path').join(__dirname,'../../public/js/voz.js'),'utf8');

function page({secure=true,supported=true}={}){
    let recognition;
    const elements=Object.fromEntries(['voice-form','voice-text','voice-mic','voice-status','voice-result','voice-mic-label'].map(id=>[id,{
        value:'Consulta escrita previamente',dataset:{},textContent:'',disabled:false,
        classList:{add(){},remove(){}},setAttribute(name,value){this[name]=value},
    }]));
    class Speech{
        constructor(){recognition=this;this.starts=0;this.stops=0}
        start(){this.starts++}
        stop(){this.stops++}
    }
    runInNewContext(source,{
        window:{isSecureContext:secure,SpeechRecognition:supported?Speech:undefined},
        document:{getElementById:id=>elements[id]},
    });
    return {elements,recognition,mic:elements['voice-mic'],status:elements['voice-status'],input:elements['voice-text']};
}

test('el error de red permanece visible al terminar y permite reintentar',()=>{
    const p=page();p.mic.onclick();p.recognition.onstart();
    p.recognition.onerror({error:'network'});p.recognition.onend();
    assert.match(p.status.textContent,/servicio de reconocimiento/);
    assert.match(p.status.textContent,/navegador integrado/);
    assert.equal(p.status.dataset.voiceError,'network');
    assert.equal(p.mic.disabled,false);
    assert.equal(p.input.value,'Consulta escrita previamente');
    p.mic.onclick();assert.equal(p.recognition.starts,2);
    assert.equal(p.status.dataset.voiceError,undefined);
});

test('distingue permiso bloqueado de ausencia de micrófono',()=>{
    const p=page();p.mic.onclick();
    p.recognition.onerror({error:'not-allowed'});p.recognition.onend();
    assert.match(p.status.textContent,/permiso del micrófono/);
    p.mic.onclick();p.recognition.onerror({error:'audio-capture'});p.recognition.onend();
    assert.match(p.status.textContent,/dispositivo de entrada/);
});

test('impide dos inicios mientras espera el reconocimiento',()=>{
    const p=page();p.mic.onclick();p.mic.onclick();
    assert.equal(p.recognition.starts,1);assert.equal(p.mic.disabled,true);
    p.recognition.onstart();p.mic.onclick();assert.equal(p.recognition.stops,1);
});

test('captura el texto y restablece el botón al terminar',()=>{
    const p=page();p.mic.onclick();p.recognition.onstart();
    assert.equal(p.elements['voice-mic-label'].textContent,'Detener');
    p.recognition.onresult({results:[[{transcript:'Mis citas canceladas'}]]});p.recognition.onend();
    assert.equal(p.input.value,'Mis citas canceladas');
    assert.match(p.status.textContent,/Voz capturada/);
    assert.equal(p.elements['voice-mic-label'].textContent,'Hablar');
    assert.equal(p.mic['aria-pressed'],'false');
});

test('sin voz no borra la consulta que ya estaba escrita',()=>{
    const p=page();p.mic.onclick();p.recognition.onstart();p.recognition.onend();
    assert.match(p.status.textContent,/No se detectó voz/);
    assert.equal(p.input.value,'Consulta escrita previamente');
});

test('explica la falta de contexto seguro o de soporte del navegador',()=>{
    const insecure=page({secure:false});assert.equal(insecure.mic.disabled,true);
    assert.match(insecure.status.textContent,/HTTPS/);
    const unsupported=page({supported:false});assert.equal(unsupported.mic.disabled,true);
    assert.match(unsupported.status.textContent,/no ofrece reconocimiento/);
});
