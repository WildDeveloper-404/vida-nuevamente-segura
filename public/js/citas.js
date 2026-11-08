(() => {
    const form=document.getElementById('booking'); if(!form)return;
    const esp=document.getElementById('id_especialidad'),med=document.getElementById('id_medico'),date=form.querySelector('[name="fecha_cita"]'),slots=document.getElementById('slots'),status=document.getElementById('slot-status'),submit=document.getElementById('book-submit');
    let doctorRequest=0,slotRequest=0,patientRequest=0;
    const get=async(url,params)=>{const r=await fetch(url+'?'+new URLSearchParams(params),{headers:{Accept:'application/json'}});const d=await r.json();if(!r.ok)throw Error(d.message||'No se pudo consultar la disponibilidad.');return d};
    const clear=()=>{slotRequest++;slots.replaceChildren();submit.disabled=true;status.textContent='Seleccione médico y fecha para ver los turnos.'};
    async function loadDoctors(){clear();const version=++doctorRequest;med.replaceChildren(new Option('Cargando…',''));med.disabled=true;if(!esp.value){med.replaceChildren(new Option('Seleccione una especialidad',''));med.disabled=false;return}try{const data=await get(form.dataset.doctors,{id_especialidad:esp.value});if(version!==doctorRequest)return;med.replaceChildren(new Option(data.length?'Seleccione médico':'No hay médicos activos',''));data.forEach(m=>med.add(new Option(m.nombre,m.id_medico)))}catch(e){status.textContent=e.message}finally{if(version===doctorRequest)med.disabled=false}}
    async function loadSlots(){clear();if(!med.value||!date.value)return;const version=++slotRequest;status.textContent='Consultando turnos…';try{const data=await get(form.dataset.slots,{id_medico:med.value,fecha:date.value});if(version!==slotRequest)return;status.textContent=data.length?'Elija un bloque disponible. Los horarios grises están ocupados.':'No hay turnos configurados para esta fecha.';data.forEach((s,i)=>{const col=document.createElement('div');col.className='col';const radio=document.createElement('input');radio.type='radio';radio.name='hora_inicio';radio.value=s.hora_inicio.slice(0,5);radio.id='slot-'+i;radio.className='btn-check';radio.required=true;radio.disabled=s.estado!=='Disponible';const label=document.createElement('label');label.className='btn btn-outline-primary slot-label';label.htmlFor=radio.id;label.textContent=s.hora_inicio.slice(0,5)+'–'+s.hora_fin.slice(0,5);if(radio.disabled)label.classList.add('disabled');radio.onchange=()=>submit.disabled=false;col.append(radio,label);slots.append(col)})}catch(e){if(version===slotRequest)status.textContent=e.message}}
    esp.onchange=loadDoctors;med.onchange=loadSlots;date.onchange=loadSlots;if(esp.value)loadDoctors();
    const referral = document.getElementById('id_derivacion');
    const referralField = document.getElementById('referral-field');
    referral.addEventListener('change', () => {
        const specialty = referral.selectedOptions[0]?.dataset.specialty;
        if (specialty && esp.value !== specialty) {
            esp.value = specialty;
            loadDoctors();
        }
    });
    esp.addEventListener('change', () => {
        if (referral.value && referral.selectedOptions[0]?.dataset.specialty !== esp.value) referral.value = '';
    });
    const search = document.getElementById('buscar-paciente');
    if (search) {
        let timer, referralRequest = 0;
        const select = document.getElementById('id_paciente');
        const clearReferrals = () => {
            referralRequest++;
            referral.replaceChildren(new Option('Nueva cita sin derivación', ''));
            referralField.hidden = true;
        };
        select.addEventListener('change', async () => {
            clearReferrals();
            if (!select.value) return;
            const version = referralRequest;
            try {
                const data = await get(form.dataset.referrals, {id_paciente: select.value});
                if (version !== referralRequest) return;
                for (const order of data) {
                    const option = new Option(order.codigo_derivacion + ' · ' + order.prioridad, order.id_derivacion);
                    option.dataset.specialty = order.id_especialidad_destino;
                    referral.add(option);
                }
                if (data.length) {
                    referralField.hidden = false;
                    referral.value = data[0].id_derivacion;
                    referral.dispatchEvent(new Event('change'));
                }
            } catch (e) {
                if (version === referralRequest) status.textContent = e.message;
            }
        });
        search.addEventListener('input', () => {
            clearTimeout(timer);
            clearReferrals();
            const version = ++patientRequest;
            select.replaceChildren(new Option('Seleccione un paciente', ''));
            if (search.value.trim().length < 2) return;
            timer = setTimeout(async () => {
                try {
                    const data = await get(form.dataset.search, {q: search.value});
                    if (version !== patientRequest) return;
                    select.replaceChildren(new Option(data.length ? 'Seleccione paciente' : 'Sin coincidencias', ''));
                    for (const patient of data) {
                        select.add(new Option(patient.ci + ' · ' + patient.nombres + ' ' + patient.apellidos, patient.id_paciente));
                    }
                } catch (e) {
                    if (version === patientRequest) status.textContent = e.message;
                }
            }, 250);
        });
    }
    form.addEventListener('submit',()=>{submit.disabled=true;submit.textContent='Reservando…'});
})();
