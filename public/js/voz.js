(() => {
    const form=document.getElementById('voice-form');if(!form)return;
    const input=document.getElementById('voice-text'),mic=document.getElementById('voice-mic'),status=document.getElementById('voice-status'),result=document.getElementById('voice-result');
    const Speech=window.SpeechRecognition||window.webkitSpeechRecognition;
    const micLabel=document.getElementById('voice-mic-label');
    const voiceErrors={
        'not-allowed':'El acceso al micrófono está bloqueado. Revise el permiso del micrófono para esta página y el acceso de este navegador en Windows.',
        'service-not-allowed':'Este navegador no permite usar el servicio de reconocimiento de voz. Abra esta página en Google Chrome o Microsoft Edge y vuelva a probar.',
        'audio-capture':'No se pudo acceder al micrófono. Compruebe que esté conectado y seleccionado como dispositivo de entrada en Windows.',
        'network':'No se pudo conectar con el servicio de reconocimiento de voz del navegador. Compruebe su conexión; si está usando el navegador integrado, pruebe esta página en Google Chrome o Microsoft Edge.',
        'no-speech':'No se detectó voz. Pulse Hablar y diga la consulta cuando aparezca «Escuchando».',
        'language-not-supported':'El servicio no admite el idioma español configurado. Pruebe esta página en otro navegador con reconocimiento de voz.',
        'aborted':'La captura de voz se interrumpió. Puede pulsar Hablar para intentarlo de nuevo.',
    };
    const canRecord=!!(window.MediaRecorder && window.navigator?.mediaDevices?.getUserMedia && form.dataset.transcriptionUrl);
    if(window.isSecureContext && canRecord){
        let busy=false,recorder=null,stream=null,timer=null,chunks=[],failed=false;
        const stopTracks=()=>{if(stream)stream.getTracks().forEach(track=>track.stop());stream=null};
        const reset=()=>{clearTimeout(timer);stopTracks();recorder=null;busy=false;mic.disabled=false;mic.classList.remove('mic-listening');mic.setAttribute('aria-pressed','false');if(micLabel)micLabel.textContent='Hablar'};
        const fail=error=>{status.textContent=error?.name==='NotAllowedError'?voiceErrors['not-allowed']:error?.name==='NotFoundError'?voiceErrors['audio-capture']:(error?.message||'No se pudo grabar el audio. Puede escribir su consulta.');reset()};
        mic.onclick=async()=>{
            if(recorder?.state==='recording'){recorder.stop();return}
            if(busy)return;
            busy=true;mic.disabled=true;status.textContent='Solicitando acceso al micrófono…';
            try{
                stream=await window.navigator.mediaDevices.getUserMedia({audio:true});
                const mime=['audio/webm;codecs=opus','audio/ogg;codecs=opus','audio/mp4'].find(type=>window.MediaRecorder.isTypeSupported(type));
                recorder=new window.MediaRecorder(stream,mime?{mimeType:mime}:undefined);chunks=[];failed=false;
                recorder.ondataavailable=event=>{if(event.data.size)chunks.push(event.data)};
                recorder.onerror=()=>{failed=true;fail(new Error('No se pudo grabar el audio. Intente de nuevo.'))};
                recorder.onstop=async()=>{
                    if(failed)return;
                    clearTimeout(timer);stopTracks();mic.disabled=true;mic.classList.remove('mic-listening');mic.setAttribute('aria-pressed','false');
                    if(micLabel)micLabel.textContent='Transcribiendo…';status.textContent='Transcribiendo su consulta…';
                    try{
                        const audio=new Blob(chunks,{type:recorder.mimeType||mime||'audio/webm'});
                        if(!audio.size)throw Error('No se capturó audio. Vuelva a grabar.');
                        if(audio.size>6*1024*1024)throw Error('El audio es demasiado grande. Grabe una frase más corta.');
                        const data=new FormData();data.append('audio',audio,'consulta.'+(audio.type.includes('mp4')?'mp4':audio.type.includes('ogg')?'ogg':'webm'));
                        const response=await fetch(form.dataset.transcriptionUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data});
                        const result=await response.json();
                        if(!response.ok)throw Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'No se pudo transcribir el audio.');
                        if(typeof result.texto!=='string'||!result.texto.trim())throw Error('No se detectó voz clara. Vuelva a grabar.');
                        input.value=result.texto;status.textContent='Voz capturada. Revise su consulta y pulse Consultar.';
                    }catch(error){status.textContent=error.message||'No se pudo transcribir el audio. Puede escribir su consulta.'}
                    finally{chunks=[];reset()}
                };
                recorder.start();mic.disabled=false;mic.classList.add('mic-listening');mic.setAttribute('aria-pressed','true');if(micLabel)micLabel.textContent='Detener';status.textContent='Grabando… Pulse Detener cuando termine (máximo 30 segundos).';
                timer=setTimeout(()=>{if(recorder?.state==='recording')recorder.stop()},30000);
            }catch(error){fail(error)}
        };
        window.addEventListener('pagehide',()=>{failed=true;clearTimeout(timer);if(recorder?.state==='recording')recorder.stop();stopTracks()});
    }else if(!window.isSecureContext){
        mic.disabled=true;status.textContent='Para usar el micrófono, abra la aplicación mediante HTTPS o desde http://127.0.0.1:8000 en este equipo.';
    }else if(Speech){
        const recognition=new Speech();
        recognition.lang='es-VE';recognition.interimResults=true;recognition.continuous=false;
        let listening=false,starting=false,hadError=false,receivedSpeech=false;
        const reset=()=>{
            listening=false;starting=false;mic.disabled=false;mic.classList.remove('mic-listening');
            mic.setAttribute('aria-pressed','false');if(micLabel)micLabel.textContent='Hablar';
        };
        const showError=code=>{
            hadError=true;status.dataset.voiceError=code;
            status.textContent=voiceErrors[code]||'No se pudo iniciar el reconocimiento de voz. Puede escribir la consulta o probar otro navegador.';
        };
        mic.onclick=()=>{
            if(starting)return;
            if(listening){recognition.stop();return}
            starting=true;hadError=false;receivedSpeech=false;mic.disabled=true;
            delete status.dataset.voiceError;status.textContent='Iniciando el reconocimiento de voz…';
            try{recognition.start()}catch(error){showError(error.name==='NotAllowedError'?'not-allowed':'start-failed');reset()}
        };
        recognition.onstart=()=>{
            starting=false;listening=true;mic.disabled=false;mic.classList.add('mic-listening');
            mic.setAttribute('aria-pressed','true');if(micLabel)micLabel.textContent='Detener';status.textContent='Escuchando…';
        };
        recognition.onresult=event=>{
            const text=Array.from(event.results).map(result=>result[0]?.transcript||'').join(' ').trim();
            if(text){input.value=text;receivedSpeech=true}
        };
        recognition.onerror=event=>showError(event.error||'unknown');
        recognition.onend=()=>{
            reset();
            if(!hadError)status.textContent=receivedSpeech?'Voz capturada. Revise su consulta y pulse Consultar.':voiceErrors['no-speech'];
        };
    }else{mic.disabled=true;status.textContent='Este navegador no ofrece reconocimiento de voz. Puede escribir su consulta o abrir esta página en Google Chrome o Microsoft Edge.'}
    form.onsubmit=async e=>{e.preventDefault();const button=document.getElementById('voice-submit');button.disabled=true;result.hidden=true;status.textContent='Interpretando consulta…';try{const r=await fetch(form.dataset.url,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({texto:input.value})});const data=await r.json();if(!r.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'No se pudo generar el reporte.');document.getElementById('voice-summary').textContent=data.resumen;const table=document.getElementById('voice-table');table.replaceChildren();const head=table.createTHead().insertRow();Object.values(data.columns).forEach(v=>{const th=document.createElement('th');th.textContent=v;head.append(th)});const body=table.createTBody();data.rows.forEach(row=>{const tr=body.insertRow();Object.keys(data.columns).forEach(k=>tr.insertCell().textContent=row[k]??'—')});result.hidden=false;status.textContent='Consulta completada.'}catch(error){status.textContent=error.message}finally{button.disabled=false}};
})();
