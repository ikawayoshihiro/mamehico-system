<?php
// 銀座ランチ管理 ③ メールテンプレート・予約一覧
// v3.0.2 - 2026-03-10

if (!function_exists('mamehico_ginza_render_mail')):
function mamehico_ginza_render_mail() {
    $saved = false;
    if (isset($_POST['mamehico_mail_save']) && check_admin_referer('mamehico_ginza_mail_nonce')) {
        update_option('mamehico_mail_customer_subject', sanitize_text_field($_POST['customer_subject']));
        update_option('mamehico_mail_customer_body',    sanitize_textarea_field($_POST['customer_body']));
        update_option('mamehico_mail_admin_subject',    sanitize_text_field($_POST['admin_subject']));
        update_option('mamehico_mail_admin_body',       sanitize_textarea_field($_POST['admin_body']));
        $saved = true;
    }

    $cs = esc_textarea(get_option('mamehico_mail_customer_subject', 'ご予約が確定いたしました（MAMEHICO 銀座）'));
    $cb = esc_textarea(get_option('mamehico_mail_customer_body', ''));
    $as = esc_textarea(get_option('mamehico_mail_admin_subject', '【予約入りました】{date} {slot}　{name} 様 {count}名'));
    $ab = esc_textarea(get_option('mamehico_mail_admin_body', ''));

    $vars = ['{name}','{email}','{phone}','{date}','{slot}','{count}','{payment}','{subtotal}','{tax}','{grand_total}'];

    echo mamehico_ginza_admin_styles();
    echo '<div class="mm-admin-wrap">
<h1>メールテンプレート（銀座ランチ）</h1>
<p class="mm-desc">予約確定時に自動送信されるメールの文面を編集できます。</p>
' . ($saved ? '<div class="mm-saved">✓ 保存しました</div>' : '') . '
<form method="post">
' . wp_nonce_field('mamehico_ginza_mail_nonce', '_wpnonce', true, false) . '
<div class="mm-vars"><span>使える変数</span>' .
    implode('', array_map(function($v) { return '<code class="mm-var">' . $v . '</code>'; }, $vars)) .
'</div>
<div class="mm-section">
<div class="mm-section-header">お客様への確認メール</div>
<div class="mm-field"><label class="mm-label">件名</label><input class="mm-input" type="text" name="customer_subject" value="' . $cs . '"></div>
<div class="mm-field"><label class="mm-label">本文</label><textarea class="mm-textarea" name="customer_body" rows="14">' . $cb . '</textarea></div>
</div>
<div class="mm-section">
<div class="mm-section-header light">管理者通知メール（info@mamehico.com）</div>
<div class="mm-field"><label class="mm-label">件名</label><input class="mm-input" type="text" name="admin_subject" value="' . $as . '"></div>
<div class="mm-field"><label class="mm-label">本文</label><textarea class="mm-textarea" name="admin_body" rows="14">' . $ab . '</textarea></div>
</div>
<button type="submit" name="mamehico_mail_save" class="mm-save-btn">保存する</button>
</form></div>';
}
endif;

// ============================================================
// 予約一覧（月単位・人数編集・削除・booked連動）
// ============================================================
if (!function_exists('mamehico_ginza_render_reservations')):
function mamehico_ginza_render_reservations() {
    $project_id = 'mamehico-schedule';
    $api_key    = 'AIzaSyDnDy4ipNbMbzXd2yurCgVwKjyEkp3FCZE';
    echo mamehico_ginza_admin_styles();
    ?>
    <style>
    .mm-filter-row{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
    .mm-filter-row input[type=month],.mm-filter-row select{border:1px solid #ddd;border-radius:3px;padding:7px 10px;font-size:13px;color:#2E303E;background:#fff}
    .mm-search-btn{background:#2E303E;color:#fff;border:none;border-radius:3px;padding:7px 16px;font-size:13px;cursor:pointer;font-family:inherit}
    #mm-slot-panel{margin-bottom:20px;padding:14px 18px;background:#f8f8f7;border:1px solid #e5e3de;border-radius:4px}
    .sp-day-block{margin-bottom:12px}.sp-day-block:last-child{margin-bottom:0}
    .sp-day-label{font-size:12px;font-weight:500;color:#2E303E;margin-bottom:8px}
    .sp-slots{display:flex;gap:16px;flex-wrap:wrap}
    .sp-card{font-size:11px;color:#2E303E;min-width:60px}
    .sp-time{font-weight:500;margin-bottom:3px;font-size:12px}
    .sp-bar-w{width:60px;height:3px;background:#e5e3de;border-radius:2px;margin-bottom:2px}
    .sp-bar{height:3px;border-radius:2px;background:#2E303E}.sp-bar.full{background:#c0392b}
    .sp-nums{color:#888}
    #mm-bulk-bar{display:none;align-items:center;gap:10px;padding:8px 14px;background:#fff3cd;border:1px solid #ffc107;border-radius:3px;margin-bottom:12px;font-size:13px}
    .mm-bulk-del-btn{background:#c0392b;color:#fff;border:none;border-radius:3px;padding:5px 14px;font-size:12px;cursor:pointer;font-family:inherit}
    .mm-res-wrap{border:1px solid #e5e3de;border-radius:4px;overflow-x:auto}
    .mm-res-table{width:100%;border-collapse:collapse;font-size:13px}
    .mm-res-table thead{background:#2E303E;color:#fff}
    .mm-res-table th{padding:10px 12px;text-align:left;font-weight:400;letter-spacing:.05em;white-space:nowrap}
    .mm-res-table td{padding:9px 12px;border-bottom:1px solid #f0eeea;color:#2E303E;vertical-align:middle;white-space:nowrap}
    .mm-res-table td.wrap{white-space:normal}
    .mm-res-table tr:last-child td{border-bottom:none}
    .mm-res-table tr:hover td{background:#faf9f7}
    .mm-res-table tr.editing td{background:#fffbf0}
    .mm-res-table tr.selected td{background:#fff8e1}
    .mm-badge{display:inline;padding:2px 7px;border-radius:2px;font-size:11px;font-weight:500}
    .mm-badge.cash{background:#f0f7f1;color:#2d7a4f;border:1px solid #a8d5b5}
    .mm-badge.card{background:#f0f4ff;color:#2c4db0;border:1px solid #b0bff5}
    .mm-res-footer{background:#f8f8f7;padding:10px 14px;font-size:13px;color:#888;border-top:1px solid #e5e3de}
    .mm-res-footer strong{color:#2E303E}
    .mm-act-btn{border:none;border-radius:3px;padding:4px 10px;font-size:11px;cursor:pointer;font-family:inherit;font-weight:500;margin-right:3px}
    .mm-act-btn.edit{background:#f0f0f0;color:#2E303E;border:1px solid #ddd}
    .mm-act-btn.edit:hover{background:#2E303E;color:#fff}
    .mm-act-btn.del{background:#fdf0f0;color:#c0392b;border:1px solid #f0c0c0}
    .mm-act-btn.del:hover{background:#c0392b;color:#fff}
    .mm-act-btn.save{background:#2d7a4f;color:#fff;border:1px solid #2d7a4f}
    .mm-act-btn.save:hover{opacity:.8}
    .mm-act-btn.cancel{background:#f0f0f0;color:#888;border:1px solid #ddd}
    .mm-cnt-input{width:52px;padding:3px 6px;border:1px solid #aaa;border-radius:3px;font-size:13px;text-align:center;font-family:inherit}
    </style>

    <div class="mm-admin-wrap">
    <h1>予約一覧（銀座ランチ）</h1>

    <div class="mm-filter-row">
        <input type="month" id="mm-month" value="<?php echo esc_attr(date('Y-m')); ?>">
        <select id="mm-payment-filter">
            <option value="">すべての支払い</option>
            <option value="cash">店頭払い</option>
            <option value="card">カード</option>
        </select>
        <button class="mm-search-btn" onclick="mmLoad()">表示</button>
    </div>

    <div id="mm-slot-panel"><div id="mm-slot-inner">読み込み中...</div></div>

    <div id="mm-bulk-bar">
        <span id="mm-bulk-count">0件選択中</span>
        <button class="mm-bulk-del-btn" onclick="mmDeleteSelected()">選択した予約を削除</button>
        <button style="background:transparent;border:none;color:#888;cursor:pointer;font-size:12px" onclick="mmClearSelection()">選択解除</button>
    </div>

    <div class="mm-res-wrap">
        <table class="mm-res-table">
            <thead><tr>
                <th style="width:28px"><input type="checkbox" id="mm-check-all" onchange="mmToggleAll(this.checked)"></th>
                <th>日付</th><th>時間帯</th><th>人数</th>
                <th>氏名</th><th>メール</th><th>電話</th><th>支払い</th><th>操作</th>
            </tr></thead>
            <tbody id="mm-tbody"><tr><td colspan="9" style="padding:30px;text-align:center;color:#aaa">読み込み中...</td></tr></tbody>
        </table>
        <div class="mm-res-footer" id="mm-total" style="display:none"></div>
    </div>
    </div>

    <script>
    const PID='<?php echo $project_id; ?>',AKEY='<?php echo $api_key; ?>';
    const SLOTS=['11:30','12:30','13:30','14:30'];
    const ENDS={'11:30':'13:30','12:30':'14:30','13:30':'15:30','14:30':'16:30'};
    const KEYS={'11:30':'1130','12:30':'1230','13:30':'1330','14:30':'1430'};
    const CAP=10,BUSINESS_DOW=[2,3,4,5];
    const rowData={};

    async function fsQuery(col,filters,orderBy){
        const url='https://firestore.googleapis.com/v1/projects/'+PID+'/databases/(default)/documents:runQuery?key='+AKEY;
        const where=filters.length===1?filters[0]:{compositeFilter:{op:'AND',filters:filters}};
        const q={from:[{collectionId:col}],where:where,limit:500};
        if(orderBy)q.orderBy=orderBy;
        const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({structuredQuery:q})});
        if(!r.ok)throw new Error('Query failed: '+r.status);
        return r.json();
    }
    async function fsGet(col,id){
        const r=await fetch('https://firestore.googleapis.com/v1/projects/'+PID+'/databases/(default)/documents/'+col+'/'+id+'?key='+AKEY);
        return r.json();
    }
    async function fsDel(col,id){
        const r=await fetch('https://firestore.googleapis.com/v1/projects/'+PID+'/databases/(default)/documents/'+col+'/'+id+'?key='+AKEY,{method:'DELETE'});
        if(!r.ok)throw new Error('Delete failed: '+r.status);
    }
    async function fsPatch(col,id,plainData){
        const fields={};
        for(const k of Object.keys(plainData)){
            const v=plainData[k];
            fields[k]=typeof v==='number'?{integerValue:String(Math.round(v))}:{stringValue:String(v)};
        }
        const mask=Object.keys(plainData).map(k=>'updateMask.fieldPaths='+encodeURIComponent(k)).join('&');
        const url='https://firestore.googleapis.com/v1/projects/'+PID+'/databases/(default)/documents/'+col+'/'+id+'?key='+AKEY+'&'+mask;
        const r=await fetch(url,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({fields:fields})});
        if(!r.ok)throw new Error('Patch failed: '+r.status);
        return r.json();
    }
    function gs(f,k){return f&&f[k]?(f[k].stringValue||String(f[k].integerValue||'')):''}

    async function adjustBooked(slotDocId,delta){
        if(!delta)return;
        try{
            const r=await fsGet('ginza_slots',slotDocId);
            const cur=parseInt(r.fields&&r.fields.booked?r.fields.booked.integerValue||0:0);
            await fsPatch('ginza_slots',slotDocId,{booked:Math.max(0,cur+delta)});
        }catch(e){console.warn('booked調整エラー',slotDocId,e);}
    }

    function renderRow(rid,editing){
        const d=rowData[rid];if(!d)return '';
        const badge=d.payment==='cash'?'<span class="mm-badge cash">店頭</span>':'<span class="mm-badge card">カード</span>';
        const slotLabel=d.slot+(ENDS[d.slot]?' — '+ENDS[d.slot]:'');
        const countCell=editing?'<input type="number" id="cnt-'+rid+'" class="mm-cnt-input" value="'+d.count+'" min="1" max="20">名':d.count+'名';
        const actionCell=editing
            ?'<button class="mm-act-btn save" onclick="mmSaveEdit(\''+rid+'\')" >保存</button><button class="mm-act-btn cancel" onclick="mmCancelEdit(\''+rid+'\')" >×</button>'
            :'<button class="mm-act-btn edit" onclick="mmStartEdit(\''+rid+'\')" >編集</button><button class="mm-act-btn del" onclick="mmDeleteOne(\''+rid+'\')" >削除</button>';
        return '<tr id="row-'+rid+'"'+(editing?' class="editing"':'')+'>'  
            +'<td><input type="checkbox" class="mm-row-check" data-id="'+rid+'" onchange="mmOnCheck()"></td>'
            +'<td>'+d.date+'</td><td>'+slotLabel+'</td><td>'+countCell+'</td>'
            +'<td>'+d.name+'</td>'
            +'<td class="wrap"><a href="mailto:'+d.email+'" style="color:#2E303E">'+d.email+'</a></td>'
            +'<td>'+(d.phone||'—')+'</td><td>'+badge+'</td><td>'+actionCell+'</td></tr>';
    }

    function mmStartEdit(rid){const row=document.getElementById('row-'+rid);if(row)row.outerHTML=renderRow(rid,true);const inp=document.getElementById('cnt-'+rid);if(inp)inp.focus();}
    function mmCancelEdit(rid){const row=document.getElementById('row-'+rid);if(row)row.outerHTML=renderRow(rid,false);}
    async function mmSaveEdit(rid){
        const inp=document.getElementById('cnt-'+rid);if(!inp)return;
        const newCount=parseInt(inp.value);
        if(!newCount||newCount<1){alert('人数を正しく入力してください');return;}
        const d=rowData[rid],oldCount=d.count;
        if(newCount===oldCount){mmCancelEdit(rid);return;}
        try{
            await fsPatch('ginza_reservations',rid,{count:newCount});
            const key=KEYS[d.slot];if(key)await adjustBooked(d.date+'_'+key,newCount-oldCount);
            rowData[rid].count=newCount;
            const row=document.getElementById('row-'+rid);if(row)row.outerHTML=renderRow(rid,false);
            updateFooter();
        }catch(e){alert('保存に失敗しました: '+e.message);}
    }
    async function mmDeleteOne(rid){
        const d=rowData[rid];
        if(!confirm(d.date+' '+d.slot+' '+d.name+'様（'+d.count+'名）の予約を削除しますか？'))return;
        try{
            const key=KEYS[d.slot];if(key)await adjustBooked(d.date+'_'+key,-d.count);
            await fsDel('ginza_reservations',rid);
            delete rowData[rid];
            const row=document.getElementById('row-'+rid);if(row)row.remove();
            updateFooter();
        }catch(e){alert('削除に失敗しました: '+e.message);}
    }
    function updateFooter(){
        const pm=document.getElementById('mm-payment-filter').value;
        const visible=Object.keys(rowData).filter(k=>!pm||rowData[k].payment===pm);
        const tp=visible.reduce((s,k)=>s+(rowData[k].count||0),0);
        const ft=document.getElementById('mm-total');
        if(visible.length){ft.style.display='block';ft.innerHTML='<strong>'+visible.length+'件</strong>　計 <strong>'+tp+'名</strong>';}
        else ft.style.display='none';
    }
    function mmOnCheck(){
        const checked=document.querySelectorAll('.mm-row-check:checked');
        const bar=document.getElementById('mm-bulk-bar');
        bar.style.display=checked.length>0?'flex':'none';
        document.getElementById('mm-bulk-count').textContent=checked.length+'件選択中';
        document.querySelectorAll('.mm-row-check').forEach(cb=>{const row=document.getElementById('row-'+cb.dataset.id);if(row)row.classList.toggle('selected',cb.checked);});
    }
    function mmToggleAll(checked){document.querySelectorAll('.mm-row-check').forEach(cb=>{cb.checked=checked;});mmOnCheck();}
    function mmClearSelection(){
        document.querySelectorAll('.mm-row-check').forEach(cb=>{cb.checked=false;});
        const all=document.getElementById('mm-check-all');if(all)all.checked=false;
        document.getElementById('mm-bulk-bar').style.display='none';
        document.querySelectorAll('.mm-res-table tr.selected').forEach(r=>r.classList.remove('selected'));
    }
    async function mmDeleteSelected(){
        const checked=Array.from(document.querySelectorAll('.mm-row-check:checked'));
        if(!checked.length)return;
        if(!confirm(checked.length+'件の予約を削除しますか？'))return;
        for(const cb of checked){
            const rid=cb.dataset.id,d=rowData[rid];if(!d)continue;
            try{const key=KEYS[d.slot];if(key)await adjustBooked(d.date+'_'+key,-d.count);await fsDel('ginza_reservations',rid);delete rowData[rid];const row=document.getElementById('row-'+rid);if(row)row.remove();}
            catch(e){console.warn('削除エラー',rid,e);}
        }
        mmClearSelection();updateFooter();
    }

    async function mmLoad(){
        const monthVal=document.getElementById('mm-month').value;if(!monthVal)return;
        const parts=monthVal.split('-'),y=parseInt(parts[0]),m=parseInt(parts[1]);
        const firstDay=monthVal+'-01',lastDay=monthVal+'-'+String(new Date(y,m,0).getDate()).padStart(2,'0');
        document.getElementById('mm-tbody').innerHTML='<tr><td colspan="9" style="padding:30px;text-align:center;color:#aaa">読み込み中...</td></tr>';
        document.getElementById('mm-total').style.display='none';
        mmClearSelection();Object.keys(rowData).forEach(k=>delete rowData[k]);
        const filters=[
            {fieldFilter:{field:{fieldPath:'date'},op:'GREATER_THAN_OR_EQUAL',value:{stringValue:firstDay}}},
            {fieldFilter:{field:{fieldPath:'date'},op:'LESS_THAN_OR_EQUAL',value:{stringValue:lastDay}}}
        ];
        try{
            const res=await fsQuery('ginza_reservations',filters,[{field:{fieldPath:'date'},direction:'ASCENDING'}]);
            const rows=res.filter(r=>r.document&&r.document.fields);
            rows.sort((a,b)=>{const d1=gs(a.document.fields,'date'),d2=gs(b.document.fields,'date');if(d1!==d2)return d1.localeCompare(d2);return gs(a.document.fields,'slot').localeCompare(gs(b.document.fields,'slot'));});
            rows.forEach(r=>{const f=r.document.fields,rid=r.document.name.split('/').pop();rowData[rid]={date:gs(f,'date'),slot:gs(f,'slot'),count:parseInt(gs(f,'count'))||0,name:gs(f,'name'),email:gs(f,'email'),phone:gs(f,'phone'),payment:gs(f,'payment_method')};});
            const pm=document.getElementById('mm-payment-filter').value;
            const visibleIds=Object.keys(rowData).filter(k=>!pm||rowData[k].payment===pm);
            if(!visibleIds.length){document.getElementById('mm-tbody').innerHTML='<tr><td colspan="9" style="padding:30px;text-align:center;color:#aaa">この月の予約はありません</td></tr>';return;}
            document.getElementById('mm-tbody').innerHTML=visibleIds.map(rid=>renderRow(rid,false)).join('');
            updateFooter();
        }catch(e){document.getElementById('mm-tbody').innerHTML='<tr><td colspan="9" style="padding:20px;color:#c0392b">エラー: '+e.message+'</td></tr>';}
    }

    function padDate(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
    async function getUpcomingDates(baseDate,n){
        const closedSet=new Set(),base=new Date(baseDate);
        const monthKeys=new Set([base.getFullYear()+String(base.getMonth()+1).padStart(2,'0')]);
        const next=new Date(base.getFullYear(),base.getMonth()+1,1);
        monthKeys.add(next.getFullYear()+String(next.getMonth()+1).padStart(2,'0'));
        await Promise.all(Array.from(monthKeys).map(async ym=>{
            try{const r=await fsGet('ginza_closed_dates',ym);if(r.fields&&r.fields.dates&&r.fields.dates.arrayValue)(r.fields.dates.arrayValue.values||[]).forEach(v=>{if(v.stringValue)closedSet.add(v.stringValue);});}catch(e){}
        }));
        const result=[],cur=new Date(base);
        while(result.length<n){const ds=padDate(cur);if(BUSINESS_DOW.includes(cur.getDay())&&!closedSet.has(ds))result.push(ds);cur.setDate(cur.getDate()+1);if(cur.getFullYear()>base.getFullYear()+1)break;}
        return result;
    }
    async function renderSlotPanel(){
        document.getElementById('mm-slot-inner').innerHTML='読み込み中...';
        const base=new Date();base.setHours(0,0,0,0);
        const dates=await getUpcomingDates(base,3);
        const DJ=['日','月','火','水','木','金','土'];
        const blocks=await Promise.all(dates.map(async d=>{
            const parts=d.split('-'),dateObj=new Date(d);
            const label=parseInt(parts[1])+'月'+parseInt(parts[2])+'日（'+DJ[dateObj.getDay()]+'）';
            const cards=await Promise.all(SLOTS.map(async slot=>{
                try{const doc=await fsGet('ginza_slots',d+'_'+KEYS[slot]);const b=parseInt(doc.fields&&doc.fields.booked?doc.fields.booked.integerValue:0);const c=parseInt(doc.fields&&doc.fields.capacity?doc.fields.capacity.integerValue:CAP);const rem=Math.max(0,c-b),pct=Math.min(100,Math.round(b/c*100)),full=rem===0;return '<div class="sp-card"><div class="sp-time">'+slot+'</div><div class="sp-bar-w"><div class="sp-bar'+(full?' full':'')+'" style="width:'+pct+'%"></div></div><div class="sp-nums">'+b+'/'+c+' 残'+rem+'</div></div>';}
                catch(e){return '<div class="sp-card"><div class="sp-time">'+slot+'</div><div class="sp-bar-w"><div class="sp-bar" style="width:0%"></div></div><div class="sp-nums">0/'+CAP+' 残'+CAP+'</div></div>';}
            }));
            return '<div class="sp-day-block"><div class="sp-day-label">'+label+'</div><div class="sp-slots">'+cards.join('')+'</div></div>';
        }));
        document.getElementById('mm-slot-inner').innerHTML=blocks.join('');
    }

    renderSlotPanel();
    mmLoad();
    </script>
    <?php
}
endif;
