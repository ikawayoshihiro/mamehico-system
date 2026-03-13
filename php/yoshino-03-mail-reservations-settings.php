<?php
// ヨシノ管理 ② メールテンプレート・予約一覧・基本設定
// v2.3.1 - 2026-03-12 {food_box_summary}変数追加・デフォルトテンプレートに追記
// v2.3.0 - 2026-03-09 予約一覧を月単位表示に変更・人数編集・削除（booked連動）対応

// ============================================================
// メールテンプレート
// ============================================================
if (!function_exists('yoshino_mail_page')):
function yoshino_mail_page() {
    yoshino_common_css();
    $pfx = 'ymail_yoshino';
    $saved = false;
    if (isset($_POST['yoshino_mail_save']) && check_admin_referer('ymn_yoshino')) {
        update_option($pfx.'_cs', sanitize_text_field($_POST['cs']));
        update_option($pfx.'_cb', sanitize_textarea_field($_POST['cb']));
        update_option($pfx.'_as', sanitize_text_field($_POST['as']));
        update_option($pfx.'_ab', sanitize_textarea_field($_POST['ab']));
        $saved = true;
    }
    $dcs = '【ご予約完了】{title} {date} {slot}';
    $dcb = '{name} 様

{title} のご予約が確定しました。

■ 日付：{date}
■ 時間：{slot}
■ 人数：{count} 名
■ 食事：{meal_summary}
■ お弁当：{food_box_summary}
■ お支払い：{payment}
■ 合計：¥{grand_total}

ご来場をお待ちしております。

──────────────────
MAMEHICO GINZA
TEL: 03-6263-0820
──────────────────
キャンセルはお電話にてお願いいたします。';
    $das = '【予約】{title} {date} {slot} {name}様 {count}名';
    $dab = '新規予約が入りました。

【タイトル】{title}
【日付】{date}
【時間】{slot}
【人数】{count} 名
【食事】{meal_summary}
【お弁当】{food_box_summary}
【コイン】{coin_summary}
【お支払い】{payment}
【合計】¥{grand_total}

【氏名】{name}
【メール】{email}
【電話】{phone}';
    $cs = esc_textarea(get_option($pfx.'_cs', $dcs));
    $cb = esc_textarea(get_option($pfx.'_cb', $dcb));
    $as = esc_textarea(get_option($pfx.'_as', $das));
    $ab = esc_textarea(get_option($pfx.'_ab', $dab));
    $vars = array('{name}','{email}','{phone}','{title}','{date}','{slot}','{count}','{meal_summary}','{food_box_summary}','{coin_summary}','{payment}','{grand_total}');
    $vars_html = '';
    foreach ($vars as $v) {
        $vars_html .= '<code style="font-size:12px;background:#2E303E;color:#fff;padding:3px 9px;border-radius:3px;font-family:monospace">'.$v.'</code> ';
    }
    ?>
<div class="ye-wrap">
<h1>✉️ メールテンプレート</h1>
<?php if($saved) echo '<p style="color:#2d7a4f;background:#f0faf4;border:1px solid #a8d5b5;padding:8px 16px;border-radius:4px;font-size:13px;margin-bottom:16px">✓ 保存しました</p>'; ?>
<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:24px;padding:14px 18px;background:#f8f8f7;border:1px solid #e5e3de;border-radius:4px"><?php echo $vars_html; ?></div>
<style>
.ym-sec{margin-bottom:28px;border:1px solid #e5e3de;border-radius:6px;overflow:hidden}
.ym-sh{padding:13px 20px;background:#2E303E;color:#fff;font-size:13px;font-weight:500}
.ym-sh.light{background:#f8f8f7;color:#2E303E;border-bottom:1px solid #e5e3de}
.ym-body{padding:16px 20px}
.ym-lbl{display:block;font-size:11px;letter-spacing:.1em;color:#999;margin-bottom:6px;font-weight:500}
.ym-in{width:100%;border:1px solid #ddd;border-radius:3px;padding:9px 12px;font-size:14px;font-family:inherit;box-sizing:border-box;margin-bottom:14px}
.ym-in:focus{border-color:#2E303E;outline:none}
.ym-ta{width:100%;border:1px solid #ddd;border-radius:3px;padding:10px 12px;font-size:13px;font-family:monospace;background:#fafaf9;line-height:1.7;resize:vertical;box-sizing:border-box}
.ym-ta:focus{border-color:#2E303E;outline:none;background:#fff}
.ym-save{background:#2E303E;color:#fff;border:none;border-radius:4px;padding:12px 36px;font-size:14px;cursor:pointer;font-family:inherit;font-weight:500}
.ym-save:hover{opacity:.75}
</style>
<form method="post">
<?php echo wp_nonce_field('ymn_yoshino','_wpnonce',true,false); ?>
<div class="ym-sec">
<div class="ym-sh">お客様への確認メール</div>
<div class="ym-body">
<label class="ym-lbl">件名</label><input class="ym-in" type="text" name="cs" value="<?php echo $cs; ?>">
<label class="ym-lbl">本文</label><textarea class="ym-ta" name="cb" rows="16"><?php echo $cb; ?></textarea>
</div></div>
<div class="ym-sec">
<div class="ym-sh light">管理者通知メール（info@mamehico.com）</div>
<div class="ym-body">
<label class="ym-lbl">件名</label><input class="ym-in" type="text" name="as" value="<?php echo $as; ?>">
<label class="ym-lbl">本文</label><textarea class="ym-ta" name="ab" rows="18"><?php echo $ab; ?></textarea>
</div></div>
<button type="submit" name="yoshino_mail_save" class="ym-save">保存する</button>
</form>
</div>
    <?php
}
endif;

// ============================================================
// 予約一覧 v2.3.0
// ============================================================
if (!function_exists('yoshino_reservations_page')):
function yoshino_reservations_page() {
    $project_id = 'mamehico-schedule';
    $api_key    = 'AIzaSyDnDy4ipNbMbzXd2yurCgVwKjyEkp3FCZE';
    yoshino_common_css();
    ?>
<div class="ye-wrap">
<h1>📋 予約一覧（ヨシノ系）</h1>
<style>
.yr-filter{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
.yr-filter input[type=month],.yr-filter select{border:1px solid #ddd;border-radius:3px;padding:8px 12px;font-size:13px;color:#2E303E;background:#fff}
.yr-go{background:#2E303E;color:#fff;border:none;border-radius:3px;padding:8px 20px;font-size:13px;cursor:pointer;font-family:inherit}
.yr-go:hover{opacity:.75}
#yr-bulk-bar{display:none;align-items:center;gap:10px;padding:8px 14px;background:#fff3cd;border:1px solid #ffc107;border-radius:3px;margin-bottom:12px;font-size:13px}
.yr-bulk-del-btn{background:#c0392b;color:#fff;border:none;border-radius:3px;padding:5px 14px;font-size:12px;cursor:pointer;font-family:inherit}
.yr-tw{border:1px solid #e5e3de;border-radius:4px;overflow:auto}
.yr-t{width:100%;border-collapse:collapse;font-size:13px}
.yr-t thead{background:#2E303E;color:#fff}
.yr-t th{padding:10px 12px;text-align:left;font-weight:400;white-space:nowrap}
.yr-t td{padding:9px 12px;border-bottom:1px solid #f0eeea;color:#2E303E;vertical-align:middle;white-space:nowrap}
.yr-t tr:last-child td{border-bottom:none}
.yr-t tr:hover td{background:#faf9f7}
.yr-t tr.editing td{background:#fffbf0}
.yr-t tr.selected td{background:#fff8e1}
.yr-badge{display:inline-block;padding:2px 8px;border-radius:2px;font-size:11px;font-weight:500}
.yr-badge.card{background:#f0f4ff;color:#2c4db0;border:1px solid #b0bff5}
.yr-badge.cash{background:#f0f7f1;color:#2d7a4f;border:1px solid #a8d5b5}
.yr-foot{background:#f8f8f7;padding:10px 14px;font-size:13px;color:#888882;border-top:1px solid #e5e3de}
.yr-foot strong{color:#2E303E}
.yr-act{border:none;border-radius:3px;padding:4px 10px;font-size:11px;cursor:pointer;font-family:inherit;font-weight:500;margin-right:3px}
.yr-act.edit{background:#f0f0f0;color:#2E303E;border:1px solid #ddd}
.yr-act.edit:hover{background:#2E303E;color:#fff}
.yr-act.del{background:#fdf0f0;color:#c0392b;border:1px solid #f0c0c0}
.yr-act.del:hover{background:#c0392b;color:#fff}
.yr-act.save{background:#2d7a4f;color:#fff;border:1px solid #2d7a4f}
.yr-act.save:hover{opacity:.8}
.yr-act.cancel{background:#f0f0f0;color:#888;border:1px solid #ddd}
.yr-cnt-in{width:52px;padding:3px 6px;border:1px solid #aaa;border-radius:3px;font-size:13px;text-align:center;font-family:inherit}
</style>

<div class="yr-filter">
    <input type="month" id="yr-month" value="<?php echo esc_attr(date('Y-m')); ?>">
    <select id="yr-pm">
        <option value="">すべての支払い</option>
        <option value="cash">店頭払い</option>
        <option value="card">カード</option>
    </select>
    <button class="yr-go" onclick="yrLoad()">表示</button>
</div>

<div id="yr-bulk-bar">
    <span id="yr-bulk-count">0件選択中</span>
    <button class="yr-bulk-del-btn" onclick="yrDeleteSelected()">選択した予約を削除</button>
    <button style="background:transparent;border:none;color:#888;cursor:pointer;font-size:12px" onclick="yrClearSel()">選択解除</button>
</div>

<div class="yr-tw">
    <table class="yr-t">
        <thead><tr>
            <th style="width:28px"><input type="checkbox" id="yr-all" onchange="yrToggleAll(this.checked)"></th>
            <th>日付</th><th>時間</th><th>演目</th><th>人数</th>
            <th>氏名</th><th>メール</th><th>電話</th>
            <th>食事</th><th>お弁当</th><th>コイン</th><th>合計</th><th>支払い</th><th>操作</th>
        </tr></thead>
        <tbody id="yr-tbody"><tr><td colspan="14" style="text-align:center;padding:40px;color:#888">月を選んで「表示」を押してください</td></tr></tbody>
    </table>
    <div class="yr-foot" id="yr-foot" style="display:none"></div>
</div>
</div>

<script>
var YR_PID='<?php echo $project_id; ?>',YR_AK='<?php echo $api_key; ?>';

var yrRows={};

function yrQuery(col,filters,orderBy){
    var url='https://firestore.googleapis.com/v1/projects/'+YR_PID+'/databases/(default)/documents:runQuery?key='+YR_AK;
    var where=filters.length===1?filters[0]:{compositeFilter:{op:'AND',filters:filters}};
    var q={from:[{collectionId:col}],where:where,limit:500};
    if(orderBy)q.orderBy=orderBy;
    return fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({structuredQuery:q})}).then(function(r){
        if(!r.ok)throw new Error('Query failed: '+r.status);
        return r.json();
    });
}
function yrFsGet(col,id){
    return fetch('https://firestore.googleapis.com/v1/projects/'+YR_PID+'/databases/(default)/documents/'+col+'/'+id+'?key='+YR_AK).then(function(r){return r.json();});
}
function yrFsDel(col,id){
    return fetch('https://firestore.googleapis.com/v1/projects/'+YR_PID+'/databases/(default)/documents/'+col+'/'+id+'?key='+YR_AK,{method:'DELETE'}).then(function(r){
        if(!r.ok)throw new Error('Delete failed: '+r.status);
    });
}
function yrFsPatch(col,id,plainData){
    var fields={};
    Object.keys(plainData).forEach(function(k){
        var v=plainData[k];
        fields[k]=typeof v==='number'?{integerValue:String(Math.round(v))}:{stringValue:String(v)};
    });
    var mask=Object.keys(plainData).map(function(k){return 'updateMask.fieldPaths='+encodeURIComponent(k);}).join('&');
    var url='https://firestore.googleapis.com/v1/projects/'+YR_PID+'/databases/(default)/documents/'+col+'/'+id+'?key='+YR_AK+'&'+mask;
    return fetch(url,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({fields:fields})}).then(function(r){
        if(!r.ok)throw new Error('Patch failed: '+r.status);
        return r.json();
    });
}
function yrGv(f,k){if(!f||!f[k])return '';return f[k].stringValue!==undefined?f[k].stringValue:(f[k].integerValue?String(f[k].integerValue):'');}
function yrMealSummary(f){
    var mc=f&&f.meal_counts&&f.meal_counts.mapValue?f.meal_counts.mapValue.fields||{}:{};
    var parts=Object.keys(mc).filter(function(k){return parseInt(mc[k].integerValue||0)>0;}).map(function(k){return k+'×'+mc[k].integerValue;});
    return parts.length?parts.join(' / '):'—';
}
function yrFoodBoxSummary(f){
    if(!f||!f.food_box_selections||!f.food_box_selections.arrayValue)return '—';
    var vals=f.food_box_selections.arrayValue.values||[];
    if(!vals.length)return '—';
    var counts={};
    vals.forEach(function(v){var label=v.stringValue||'';if(label){counts[label]=(counts[label]||0)+1;}});
    var parts=Object.keys(counts).map(function(k){return k+'×'+counts[k];});
    return parts.length?parts.join('、'):'—';
}

async function yrAdjustBooked(slotDocId,delta){
    if(!delta)return;
    try{
        var r=await yrFsGet('yoshino_slots',slotDocId);
        var cur=parseInt(r.fields&&r.fields.booked?r.fields.booked.integerValue||0:0);
        await yrFsPatch('yoshino_slots',slotDocId,{booked:Math.max(0,cur+delta)});
    }catch(e){console.warn('booked調整エラー',slotDocId,e);}
}

function yrRenderRow(rid,editing){
    var d=yrRows[rid];
    if(!d)return '';
    var badge=d.payment==='card'
        ?'<span class="yr-badge card">カード</span>'
        :'<span class="yr-badge cash">店頭払い</span>';
    var slotLabel=d.slot+(d.slotEnd?' — '+d.slotEnd:'');
    var countCell=editing
        ?'<input type="number" id="yrcnt-'+rid+'" class="yr-cnt-in" value="'+d.count+'" min="1" max="50">名'
        :d.count+'名';
    var actionCell=editing
        ?'<button class="yr-act save" onclick="yrSaveEdit(\''+rid+'\')">保存</button>'
         +'<button class="yr-act cancel" onclick="yrCancelEdit(\''+rid+'\')">×</button>'
        :'<button class="yr-act edit" onclick="yrStartEdit(\''+rid+'\')">編集</button>'
         +'<button class="yr-act del" onclick="yrDeleteOne(\''+rid+'\')">削除</button>';
    return '<tr id="yrrow-'+rid+'"'+(editing?' class="editing"':'')+'>'
        +'<td><input type="checkbox" class="yr-row-check" data-id="'+rid+'" onchange="yrOnCheck()"></td>'
        +'<td>'+d.date+'</td>'
        +'<td>'+slotLabel+'</td>'
        +'<td style="font-size:12px;max-width:120px;overflow:hidden;text-overflow:ellipsis">'+(d.eventTitle||'—')+'</td>'
        +'<td>'+countCell+'</td>'
        +'<td>'+d.name+'</td>'
        +'<td><a href="mailto:'+d.email+'" style="color:#2E303E">'+d.email+'</a></td>'
        +'<td>'+(d.phone||'—')+'</td>'
        +'<td style="font-size:12px">'+d.mealSummary+'</td>'
        +'<td style="font-size:12px">'+d.foodBoxSummary+'</td>'
        +'<td>'+(d.coinTotal>0?'¥'+d.coinTotal.toLocaleString():'—')+'</td>'
        +'<td>¥'+d.grandTotal.toLocaleString()+'</td>'
        +'<td>'+badge+'</td>'
        +'<td>'+actionCell+'</td>'
        +'</tr>';
}

function yrStartEdit(rid){
    var row=document.getElementById('yrrow-'+rid);
    if(row)row.outerHTML=yrRenderRow(rid,true);
    var inp=document.getElementById('yrcnt-'+rid);
    if(inp)inp.focus();
}
function yrCancelEdit(rid){
    var row=document.getElementById('yrrow-'+rid);
    if(row)row.outerHTML=yrRenderRow(rid,false);
}
async function yrSaveEdit(rid){
    var inp=document.getElementById('yrcnt-'+rid);
    if(!inp)return;
    var newCount=parseInt(inp.value);
    if(!newCount||newCount<1){alert('人数を正しく入力してください');return;}
    var d=yrRows[rid];
    var oldCount=d.count;
    if(newCount===oldCount){yrCancelEdit(rid);return;}
    try{
        await yrFsPatch('yoshino_reservations',rid,{count:newCount});
        var slotKey=d.slot.replace(':','');
        await yrAdjustBooked(d.date+'_'+slotKey,newCount-oldCount);
        yrRows[rid].count=newCount;
        yrRows[rid].grandTotal=yrRows[rid].foodTotal+yrRows[rid].coinTotal;
        var row=document.getElementById('yrrow-'+rid);
        if(row)row.outerHTML=yrRenderRow(rid,false);
        yrUpdateFooter();
    }catch(e){alert('保存に失敗しました: '+e.message);}
}

async function yrDeleteOne(rid){
    var d=yrRows[rid];
    if(!confirm(d.date+' '+d.slot+' '+d.name+'様（'+d.count+'名）の予約を削除しますか？'))return;
    try{
        var slotKey=d.slot.replace(':','');
        await yrAdjustBooked(d.date+'_'+slotKey,-d.count);
        await yrFsDel('yoshino_reservations',rid);
        delete yrRows[rid];
        var row=document.getElementById('yrrow-'+rid);
        if(row)row.remove();
        yrUpdateFooter();
    }catch(e){alert('削除に失敗しました: '+e.message);}
}

function yrUpdateFooter(){
    var pm=document.getElementById('yr-pm').value;
    var visible=Object.keys(yrRows).filter(function(k){return !pm||yrRows[k].payment===pm;});
    var tp=visible.reduce(function(s,k){return s+(yrRows[k].count||0);},0);
    var ta=visible.reduce(function(s,k){return s+(yrRows[k].grandTotal||0);},0);
    var ft=document.getElementById('yr-foot');
    if(visible.length){
        ft.style.display='block';
        ft.innerHTML='合計 <strong>'+visible.length+'件</strong>　'+tp+'名　売上合計 <strong>¥'+ta.toLocaleString()+'</strong>';
    }else{
        ft.style.display='none';
    }
}

function yrOnCheck(){
    var checked=document.querySelectorAll('.yr-row-check:checked');
    var bar=document.getElementById('yr-bulk-bar');
    bar.style.display=checked.length>0?'flex':'none';
    document.getElementById('yr-bulk-count').textContent=checked.length+'件選択中';
    document.querySelectorAll('.yr-row-check').forEach(function(cb){
        var row=document.getElementById('yrrow-'+cb.dataset.id);
        if(row)row.classList.toggle('selected',cb.checked);
    });
}
function yrToggleAll(checked){
    document.querySelectorAll('.yr-row-check').forEach(function(cb){cb.checked=checked;});
    yrOnCheck();
}
function yrClearSel(){
    document.querySelectorAll('.yr-row-check').forEach(function(cb){cb.checked=false;});
    var all=document.getElementById('yr-all');if(all)all.checked=false;
    document.getElementById('yr-bulk-bar').style.display='none';
    document.querySelectorAll('.yr-t tr.selected').forEach(function(r){r.classList.remove('selected');});
}

async function yrDeleteSelected(){
    var checked=Array.from(document.querySelectorAll('.yr-row-check:checked'));
    if(!checked.length)return;
    if(!confirm(checked.length+'件の予約を削除しますか？'))return;
    for(var i=0;i<checked.length;i++){
        var rid=checked[i].dataset.id;
        var d=yrRows[rid];
        if(!d)continue;
        try{
            var slotKey=d.slot.replace(':','');
            await yrAdjustBooked(d.date+'_'+slotKey,-d.count);
            await yrFsDel('yoshino_reservations',rid);
            delete yrRows[rid];
            var row=document.getElementById('yrrow-'+rid);
            if(row)row.remove();
        }catch(e){console.warn('削除エラー',rid,e);}
    }
    yrClearSel();
    yrUpdateFooter();
}

async function yrLoad(){
    var monthVal=document.getElementById('yr-month').value;
    if(!monthVal)return;
    var parts=monthVal.split('-');
    var y=parseInt(parts[0]),m=parseInt(parts[1]);
    var firstDay=monthVal+'-01';
    var lastDay=monthVal+'-'+String(new Date(y,m,0).getDate()).padStart(2,'0');

    document.getElementById('yr-tbody').innerHTML='<tr><td colspan="14" style="text-align:center;padding:40px;color:#888">読み込み中...</td></tr>';
    document.getElementById('yr-foot').style.display='none';
    yrClearSel();
    Object.keys(yrRows).forEach(function(k){delete yrRows[k];});

    var filters=[
        {fieldFilter:{field:{fieldPath:'date'},op:'GREATER_THAN_OR_EQUAL',value:{stringValue:firstDay}}},
        {fieldFilter:{field:{fieldPath:'date'},op:'LESS_THAN_OR_EQUAL',value:{stringValue:lastDay}}}
    ];

    try{
        var res=await yrQuery(
            'yoshino_reservations',
            filters,
            [{field:{fieldPath:'date'},direction:'ASCENDING'}]
        );
        var rows=res.filter(function(r){return r.document&&r.document.fields;});

        rows.sort(function(a,b){
            var f1=a.document.fields,f2=b.document.fields;
            var dd=yrGv(f1,'date').localeCompare(yrGv(f2,'date'));
            if(dd!==0)return dd;
            return yrGv(f1,'slot').localeCompare(yrGv(f2,'slot'));
        });

        rows.forEach(function(r){
            var f=r.document.fields;
            var rid=r.document.name.split('/').pop();
            var ft=parseInt(f&&f.food_total?f.food_total.integerValue||0:0);
            var ct=parseInt(f&&f.coin_total?f.coin_total.integerValue||0:0);
            yrRows[rid]={
                date:yrGv(f,'date'),
                slot:yrGv(f,'slot'),
                slotEnd:yrGv(f,'slot_end'),
                count:parseInt(yrGv(f,'count'))||0,
                name:yrGv(f,'name'),
                email:yrGv(f,'email'),
                phone:yrGv(f,'phone'),
                payment:yrGv(f,'payment_method'),
                eventTitle:yrGv(f,'event_title'),
                mealSummary:yrMealSummary(f),
                foodBoxSummary:yrFoodBoxSummary(f),
                coinTotal:ct,
                foodTotal:ft,
                grandTotal:ft+ct
            };
        });

        var pm=document.getElementById('yr-pm').value;
        var visibleIds=Object.keys(yrRows).filter(function(k){return !pm||yrRows[k].payment===pm;});

        if(!visibleIds.length){
            document.getElementById('yr-tbody').innerHTML='<tr><td colspan="14" style="text-align:center;padding:40px;color:#888">この月の予約はありません</td></tr>';
            return;
        }

        document.getElementById('yr-tbody').innerHTML=visibleIds.map(function(rid){
            return yrRenderRow(rid,false);
        }).join('');
        yrUpdateFooter();

    }catch(e){
        document.getElementById('yr-tbody').innerHTML='<tr><td colspan="14" style="padding:20px;color:#c0392b">エラー: '+e.message+'</td></tr>';
    }
}

yrLoad();
</script>
    <?php
}
endif;

// ============================================================
// 基本設定
// ============================================================
if (!function_exists('yoshino_settings_page')):
function yoshino_settings_page() {
    yoshino_common_css();
    $fb = yoshino_firebase_config();
    ?>
<div class="ye-wrap">
<h1>⚙️ 基本設定</h1>
<p style="color:#888;font-size:13px;margin-bottom:24px">Firestore の <code>yoshino_events/yoshino</code> に保存されます。</p>
<div id="ys-status" style="color:#888;font-size:13px;margin-bottom:16px">読み込み中...</div>
<div class="ye-field"><label>デフォルトタイトル</label><input type="text" id="ys-title" placeholder="脱走兵と群衆"></div>
<div class="ye-grid2" style="margin-bottom:16px">
    <div class="ye-field"><label>定員</label><input type="number" id="ys-cap" placeholder="45"></div>
    <div class="ye-field"><label>所要時間</label><input type="text" id="ys-dur" placeholder="約2時間"></div>
</div>
<div class="ye-grid2" style="margin-bottom:16px">
    <div class="ye-field"><label>成功ページURL</label><input type="text" id="ys-surl" placeholder="/yoshino-success/"></div>
    <div class="ye-field"><label>キャンセルURL</label><input type="text" id="ys-curl" placeholder="/"></div>
</div>
<div class="ye-field"><label>TEL</label><input type="text" id="ys-tel" style="max-width:200px" placeholder="03-6263-0820"></div>

<p style="font-size:11px;letter-spacing:.1em;color:#999;font-weight:500;margin-bottom:4px">スロット（時間帯）</p>
<div style="display:grid;grid-template-columns:90px 90px 28px;gap:6px;margin-bottom:4px">
    <span style="font-size:11px;color:#aaa;padding-left:2px">開始</span>
    <span style="font-size:11px;color:#aaa;padding-left:2px">終了</span>
    <span></span>
</div>
<div id="ys-srows" style="margin-bottom:8px"></div>
<button id="ys-add-slot" style="border:1px dashed #ccc;background:#fafaf9;color:#888;border-radius:3px;padding:6px 12px;font-size:12px;cursor:pointer;font-family:inherit;margin-bottom:24px">＋ スロットを追加</button>

<p style="font-size:11px;letter-spacing:.1em;color:#999;font-weight:500;margin-bottom:4px">場所</p>
<div id="ys-vrows" style="margin-bottom:8px"></div>
<button id="ys-add-venue" style="border:1px dashed #ccc;background:#fafaf9;color:#888;border-radius:3px;padding:6px 12px;font-size:12px;cursor:pointer;font-family:inherit;margin-bottom:24px">＋ 場所を追加</button>

<p style="font-size:11px;letter-spacing:.1em;color:#999;font-weight:500;margin-bottom:4px">食事セット</p>
<div id="ys-frows" style="margin-bottom:8px"></div>
<button id="ys-add-food" style="border:1px dashed #ccc;background:#fafaf9;color:#888;border-radius:3px;padding:6px 12px;font-size:12px;cursor:pointer;font-family:inherit;margin-bottom:24px">＋ セットを追加</button>

<div class="ye-grid2" style="margin-bottom:24px">
    <div class="ye-field"><label>フードBOX 種類（カンマ区切り）</label><input type="text" id="ys-fboxes"></div>
    <div class="ye-field"><label>応援コイン 金額（カンマ区切り）</label><input type="text" id="ys-coins"></div>
</div>
<button id="ys-save-btn" style="padding:10px 32px;background:#2E303E;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;font-weight:bold">保存する</button>
</div>

<style>
.ys-slot-row,.ys-venue-row{display:grid;gap:6px;margin-bottom:6px;align-items:center}
.ys-slot-row{grid-template-columns:90px 90px 28px}
.ys-venue-row{grid-template-columns:1fr 28px}
.ys-slot-row input,.ys-venue-row input,.ys-food-row input{padding:7px 10px;font-size:12px;border:1px solid #ddd;border-radius:3px;box-sizing:border-box;font-family:inherit}
.ys-food-row{display:grid;grid-template-columns:1fr 90px 28px;gap:6px;margin-bottom:4px;align-items:center}
.ys-food-desc{margin-bottom:8px}
.ys-food-desc input{width:100%;padding:5px 10px;font-size:11px;border:1px solid #ddd;border-radius:3px;box-sizing:border-box;font-family:inherit;color:#888}
.ys-del{width:28px;height:28px;border:1px solid #f0d0d0;border-radius:3px;background:#fff;color:#c0392b;cursor:pointer;font-size:14px;padding:0;line-height:1}
.ys-del:hover{background:#c0392b;color:#fff}
</style>

<script type="module">
import{initializeApp,getApps}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import{getFirestore,doc,getDoc,setDoc}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js";
var fb=<?php echo $fb; ?>;
var app=getApps().find(function(a){return a.name==="ys";})||initializeApp(fb,"ys");
var db=getFirestore(app);

function setst(msg,c){var e=document.getElementById("ys-status");if(e){e.textContent=msg;e.style.color=c||"#888";}}

function addSlot(s){
    s=s||{};
    var id="sr"+Date.now()+Math.random().toString(36).substr(2,4);
    var div=document.createElement("div"); div.className="ys-slot-row"; div.id=id;
    div.innerHTML='<input type="text" class="st" placeholder="13:00" value="'+(s.time||"")+'">'
        +'<input type="text" class="se" placeholder="15:00" value="'+(s.end||"")+'">'
        +'<button class="ys-del" type="button">×</button>';
    div.querySelector(".ys-del").onclick=function(){div.remove();};
    document.getElementById("ys-srows").appendChild(div);
}

function addVenue(v){
    v=v||"";
    var id="vr"+Date.now()+Math.random().toString(36).substr(2,4);
    var div=document.createElement("div"); div.className="ys-venue-row"; div.id=id;
    div.innerHTML='<input type="text" class="vn" placeholder="MAMEHICO GINZA" value="'+v+'">'
        +'<button class="ys-del" type="button">×</button>';
    div.querySelector(".ys-del").onclick=function(){div.remove();};
    document.getElementById("ys-vrows").appendChild(div);
}

function addFood(f){
    f=f||{};
    var id="fr"+Date.now()+Math.random().toString(36).substr(2,4);
    var div=document.createElement("div"); div.id=id;
    div.innerHTML='<div class="ys-food-row">'
        +'<input type="text" class="fl" placeholder="ラベル" value="'+(f.label||"")+'">'
        +'<input type="number" class="fv" placeholder="価格" value="'+(f.value||"")+'">'
        +'<button class="ys-del" type="button">×</button>'
        +'</div><div class="ys-food-desc"><input type="text" class="fd" placeholder="説明文" value="'+(f.desc||"")+'"></div>';
    div.querySelector(".ys-del").onclick=function(){div.remove();};
    document.getElementById("ys-frows").appendChild(div);
}

document.getElementById("ys-add-slot").addEventListener("click", function(){ addSlot(); });
document.getElementById("ys-add-venue").addEventListener("click", function(){ addVenue(); });
document.getElementById("ys-add-food").addEventListener("click", function(){ addFood(); });

document.getElementById("ys-save-btn").addEventListener("click", async function(){
    var slots=[];
    document.getElementById("ys-srows").querySelectorAll(".ys-slot-row").forEach(function(row){
        var t=row.querySelector(".st").value.trim(), e=row.querySelector(".se").value.trim();
        var k=t.replace(":","");
        if(t) slots.push({time:t,end:e,key:k});
    });
    var venues=[];
    document.getElementById("ys-vrows").querySelectorAll(".ys-venue-row").forEach(function(row){
        var v=row.querySelector(".vn").value.trim();
        if(v) venues.push(v);
    });
    var foods=[];
    document.getElementById("ys-frows").querySelectorAll("[id^=fr]").forEach(function(row){
        var l=row.querySelector(".fl").value.trim(), v=Number(row.querySelector(".fv").value), d=row.querySelector(".fd").value.trim();
        if(l) foods.push({key:l,label:l,value:v,desc:d});
    });
    var data={
        title:document.getElementById("ys-title").value.trim(),
        capacity:Number(document.getElementById("ys-cap").value)||45,
        duration:document.getElementById("ys-dur").value.trim(),
        success_url:document.getElementById("ys-surl").value.trim(),
        cancel_url:document.getElementById("ys-curl").value.trim(),
        tel:document.getElementById("ys-tel").value.trim(),
        price:0, slots:slots, venues:venues, foods:foods,
        food_boxes:document.getElementById("ys-fboxes").value.split(",").map(function(s){return s.trim();}).filter(Boolean),
        coins:document.getElementById("ys-coins").value.split(",").map(function(s){return Number(s.trim());}).filter(function(n){return n>0;})
    };
    try{
        await setDoc(doc(db,"yoshino_events","yoshino"),data);
        setst("保存完了 ✓","#4caf50");
    }catch(e){setst("保存エラー: "+e.message,"#e53935");}
});

(async function(){
    try{
        var s=await getDoc(doc(db,"yoshino_events","yoshino"));
        if(s.exists()){
            var d=s.data();
            document.getElementById("ys-title").value=d.title||"";
            document.getElementById("ys-cap").value=d.capacity||45;
            document.getElementById("ys-dur").value=d.duration||"";
            document.getElementById("ys-surl").value=d.success_url||"/yoshino-success/";
            document.getElementById("ys-curl").value=d.cancel_url||"/";
            document.getElementById("ys-tel").value=d.tel||"03-6263-0820";
            document.getElementById("ys-fboxes").value=(d.food_boxes||[]).join(", ");
            document.getElementById("ys-coins").value=(d.coins||[]).join(", ");
            (d.venues||[]).forEach(function(v){addVenue(v);});
            (d.slots||[]).forEach(function(s){addSlot(s);});
            (d.foods||[]).forEach(function(f){addFood(f);});
            setst("読み込み完了","#4caf50");
        }else{
            setst("新規作成モード","#888");
        }
    }catch(e){setst("エラー: "+e.message,"#e53935");}
})();
</script>
    <?php
}
endif;
