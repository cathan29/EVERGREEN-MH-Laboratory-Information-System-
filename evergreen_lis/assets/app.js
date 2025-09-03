// EvergreenMH LIS - client-side JS
const $ = (sel, root=document) => root.querySelector(sel);
const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

function navTo(sectionId){
  $$('.menu .menu-item').forEach(b=>b.classList.toggle('active', b.dataset.section===sectionId));
  $$('.section').forEach(s=>s.classList.toggle('active', s.id===sectionId));
  if(sectionId==='dashboard'){ loadStats(); }
  if(sectionId==='patients'){ loadPatients(); loadTestsForOrder(); }
  if(sectionId==='tests'){ loadTests(); }
  if(sectionId==='workflow'){ loadWorkflow(); }
  if(sectionId==='results'){ /* nothing */ }
}

// Menu
$$('.menu .menu-item').forEach(btn=>btn.addEventListener('click',()=>navTo(btn.dataset.section)));
$('#menuToggle').addEventListener('click',()=>$('.sidebar').classList.toggle('open'));
$$('[data-nav="patients"]').forEach(b=>b.addEventListener('click',()=>navTo('patients')));
$$('[data-nav="tests"]').forEach(b=>b.addEventListener('click',()=>navTo('tests')));
$$('[data-nav="workflow"]').forEach(b=>b.addEventListener('click',()=>navTo('workflow')));
$$('[data-nav="results"]').forEach(b=>b.addEventListener('click',()=>navTo('results')));

// Dashboard
async function loadStats(){
  const r = await fetch('api.php?action=stats'); const j = await r.json();
  if(!j.ok) return;
  $('#stat-tests-today').textContent = j.data.tests_today;
  $('#stat-pending').textContent = j.data.pending;
  $('#stat-completed').textContent = j.data.completed_today;
  $('#stat-revenue').textContent = parseFloat(j.data.revenue_today).toFixed(2);
}

// Patients
$('#patientForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target).entries());
  const r = await fetch('api.php?action=patients.create', {method:'POST', body: JSON.stringify(data)});
  const j = await r.json();
  if(j.ok){ e.target.reset(); loadPatients(); alert('Patient saved.'); }
  else alert(j.error || 'Failed');
});

async function loadPatients(){
  const r = await fetch('api.php?action=patients.list'); const j = await r.json();
  if(!j.ok) return;
  const tbody = $('#patientsTable tbody'); tbody.innerHTML='';
  for(const p of j.data){
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${p.id}</td><td>${p.mrn||''}</td><td>${p.full_name}</td><td>${p.sex}</td><td>${p.dob||''}</td><td>${p.phone||''}</td>
                    <td><button class="btn" onclick="pickPatient(${p.id})">Use</button></td>`;
    tbody.appendChild(tr);
  }
}

function pickPatient(id){
  $('#orderPatientId').value = id;
  window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'});
}

async function loadTests(){
  const r = await fetch('api.php?action=tests.list'); const j = await r.json();
  if(!j.ok) return;
  const tbody = $('#testsTable tbody'); tbody.innerHTML='';
  for(const t of j.data){
    const ref = (t.ref_low!==null || t.ref_high!==null) ? `${t.ref_low ?? ''} - ${t.ref_high ?? ''}` : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${t.id}</td><td>${t.code||''}</td><td>${t.name}</td><td>${t.unit||''}</td>
                    <td>${Number(t.price).toFixed(2)}</td><td>${ref}</td>`;
    tbody.appendChild(tr);
  }
}

$('#testForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target).entries());
  if(data.price==='') data.price = 0;
  ['ref_low','ref_high','price'].forEach(k=>{ if(data[k]==='') delete data[k]; });
  const r = await fetch('api.php?action=tests.create', {method:'POST', body: JSON.stringify(data)});
  const j = await r.json();
  if(j.ok){ e.target.reset(); loadTests(); loadTestsForOrder(); alert('Test added.'); }
  else alert(j.error || 'Failed');
});

async function loadTestsForOrder(){
  const r = await fetch('api.php?action=tests.list'); const j = await r.json();
  if(!j.ok) return;
  const sel = $('#orderTests'); sel.innerHTML='';
  for(const t of j.data){
    const opt = document.createElement('option');
    opt.value = t.id;
    opt.textContent = `${t.name} (${t.unit||''}) — ₱${Number(t.price).toFixed(2)}`;
    sel.appendChild(opt);
  }
}

$('#btnCreateOrder').addEventListener('click', async ()=>{
  const patient_id = Number($('#orderPatientId').value);
  const tests = Array.from($('#orderTests').selectedOptions).map(o=>Number(o.value));
  if(!patient_id || tests.length===0){ alert('Select a patient and at least one test.'); return; }
  const r = await fetch('api.php?action=orders.create', {method:'POST', body: JSON.stringify({patient_id, tests})});
  const j = await r.json();
  if(j.ok){ alert('Order created with ID '+j.data.order_id); loadWorkflow(); navTo('workflow'); }
  else alert(j.error || 'Failed');
});

// Workflow
async function loadWorkflow(){
  const r = await fetch('api.php?action=workflow.board'); const j = await r.json();
  if(!j.ok) return;
  const cols = $$('#kanban .col-body');
  cols.forEach(c=>c.innerHTML='');
  const statuses = ['registered','collected','in_lab','result_entered','released'];
  for(const status of statuses){
    const col = $(`#kanban .col-body[data-col="${status}"]`);
    const items = j.data[status] || [];
    for(const it of items){
      const div = document.createElement('div');
      div.className = 'card-mini';
      div.innerHTML = `<div class="title">${it.test_name}</div>
                       <div class="meta">${it.full_name}</div>
                       <select onchange="updateItemStatus(${it.id}, this.value)">
                         ${statuses.map(s=>`<option value="${s}" ${s===status?'selected':''}>${s.replace('_',' ')}</option>`).join('')}
                       </select>`;
      col.appendChild(div);
    }
  }
}

async function updateItemStatus(id, status){
  const r = await fetch('api.php?action=order_items.update_status', {method:'POST', body: JSON.stringify({id, status})});
  const j = await r.json();
  if(j.ok){ loadWorkflow(); }
  else alert(j.error || 'Failed');
}

// Results
$('#btnResultsSearch').addEventListener('click', doResultsSearch);
$('#resultsQuery').addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); doResultsSearch(); } });

async function doResultsSearch(){
  const q = $('#resultsQuery').value.trim();
  const r = await fetch('api.php?action=results.search&q='+encodeURIComponent(q));
  const j = await r.json();
  if(!j.ok) return;
  const tbody = $('#resultsTable tbody'); tbody.innerHTML='';
  for(const row of j.data){
    const flag = row.is_abnormal ? `<span class="flag-badge flag-abn">Abnormal</span>` : `<span class="flag-badge flag-ok">Normal</span>`;
    const valueCell = row.result_value ?? '';
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${row.item_id}</td>
      <td>${row.full_name} (${row.mrn||'No MRN'})</td>
      <td>${row.test_name}</td>
      <td><input type="text" value="${valueCell}" id="val_${row.item_id}" style="width:120px"></td>
      <td>${row.unit||''}</td>
      <td>${row.status}</td>
      <td>${flag}</td>
      <td><button class="btn primary" onclick="saveResult(${row.item_id})">Save</button></td>`;
    tbody.appendChild(tr);
  }
}

async function saveResult(id){
  const value = $(`#val_${id}`).value.trim();
  const r = await fetch('api.php?action=results.add', {method:'POST', body: JSON.stringify({order_item_id:id, value})});
  const j = await r.json();
  if(j.ok){
    alert(j.data.is_abnormal ? 'Saved. Flagged as abnormal.' : 'Saved.');
    doResultsSearch(); loadStats();
  } else {
    alert(j.error || 'Failed');
  }
}

// Global initial load
document.addEventListener('DOMContentLoaded', ()=>{
  loadStats();
});
