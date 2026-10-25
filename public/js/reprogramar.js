document.querySelectorAll('[data-reschedule]').forEach((form,formIndex)=>{
    const date=form.querySelector('[name="fecha_cita"]'),container=form.querySelector('[data-reschedule-slots]'),status=form.querySelector('[data-reschedule-status]'),submit=form.querySelector('button[type="submit"], .modal-footer button');
    let request=0;submit.disabled=true;
    date.addEventListener('change',async()=>{
        const version=++request;container.replaceChildren();submit.disabled=true;if(!date.value)return;status.textContent='Consultando disponibilidad…';
        try {const r=await fetch(form.dataset.slots+'?'+new URLSearchParams({id_medico:form.dataset.doctor,fecha:date.value}),{headers:{Accept:'application/json'}});const data=await r.json();if(!r.ok)throw Error(data.message||'No se pudo consultar la disponibilidad.');if(version!==request)return;status.textContent=data.length?'Seleccione el nuevo bloque.':'No hay turnos para esta fecha.';
            data.forEach((slot,i)=>{const col=document.createElement('div');col.className='col';const input=document.createElement('input');input.type='radio';input.name='hora_inicio';input.value=slot.hora_inicio.slice(0,5);input.id=`reschedule-${formIndex}-${i}`;input.className='btn-check';input.required=true;input.disabled=slot.estado!=='Disponible';input.onchange=()=>submit.disabled=false;const label=document.createElement('label');label.htmlFor=input.id;label.textContent=slot.hora_inicio.slice(0,5)+'–'+slot.hora_fin.slice(0,5);label.className='btn btn-outline-primary slot-label'+(input.disabled?' disabled':'');col.append(input,label);container.append(col)});
        }catch(e){if(version===request)status.textContent=e.message}
    });
});
