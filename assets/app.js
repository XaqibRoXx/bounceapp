document.addEventListener('DOMContentLoaded',()=>{
  const navBtn=document.querySelector('[data-nav-toggle]'),nav=document.querySelector('[data-nav]');
  navBtn?.addEventListener('click',()=>nav?.classList.toggle('open'));
  document.querySelectorAll('[data-toggle]').forEach(btn=>btn.addEventListener('click',()=>{const el=document.querySelector(btn.dataset.toggle);if(el)el.hidden=!el.hidden;}));
  document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copy||'');const old=btn.textContent;btn.textContent='Copied';setTimeout(()=>btn.textContent=old,1400);}catch{prompt('Copy this link:',btn.dataset.copy||'');}}));
  document.querySelectorAll('[data-reply]').forEach(btn=>btn.addEventListener('click',()=>{const form=btn.closest('.panel')?.querySelector('.comment-form');if(!form)return;const parent=form.querySelector('[data-parent-id]'),box=form.querySelector('[data-comment-box]');if(parent)parent.value=btn.dataset.reply||'0';if(box){box.placeholder='Reply to '+(btn.dataset.replyName||'comment')+'…';box.focus();}}));
  const dialog=document.getElementById('annotationDialog');document.querySelectorAll('[data-close-dialog]').forEach(b=>b.addEventListener('click',()=>dialog?.close()));
  const stage=document.getElementById('captureStage'),draft=document.getElementById('draftBox');
  if(stage&&stage.dataset.editable==='1'&&dialog&&draft){
    let start=null,drawing=false;
    const pct=(ev)=>{const r=stage.getBoundingClientRect();return {x:Math.max(0,Math.min(100,(ev.clientX-r.left)/r.width*100)),y:Math.max(0,Math.min(100,(ev.clientY-r.top)/r.height*100))};};
    stage.addEventListener('pointerdown',ev=>{if(ev.target.closest('.ann-box'))return;drawing=true;start=pct(ev);stage.setPointerCapture?.(ev.pointerId);draft.hidden=false;draft.style.left=start.x+'%';draft.style.top=start.y+'%';draft.style.width='0%';draft.style.height='0%';});
    stage.addEventListener('pointermove',ev=>{if(!drawing||!start)return;const p=pct(ev),x=Math.min(start.x,p.x),y=Math.min(start.y,p.y),w=Math.abs(p.x-start.x),h=Math.abs(p.y-start.y);Object.assign(draft.style,{left:x+'%',top:y+'%',width:w+'%',height:h+'%'});});
    stage.addEventListener('pointerup',ev=>{if(!drawing||!start)return;drawing=false;const p=pct(ev),x=Math.min(start.x,p.x),y=Math.min(start.y,p.y),w=Math.max(1.5,Math.abs(p.x-start.x)),h=Math.max(1.5,Math.abs(p.y-start.y));Object.assign(draft.style,{left:x+'%',top:y+'%',width:w+'%',height:h+'%'});document.getElementById('xPct').value=x.toFixed(4);document.getElementById('yPct').value=y.toFixed(4);document.getElementById('wPct').value=w.toFixed(4);document.getElementById('hPct').value=h.toFixed(4);dialog.showModal();});
    dialog.addEventListener('close',()=>{draft.hidden=true;start=null;drawing=false;});
  }
});
