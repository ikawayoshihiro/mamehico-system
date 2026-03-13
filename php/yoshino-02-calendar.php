<?php
// ヨシノ管理 ② 公演日カレンダー v2.0.2
// v2.0.2 - 2026-03-11 保存前にsyncTimesを強制実行（time入力フォーカス中でも保存可）
// v2.0.1 - 2026-03-11 カレンダーセルに会場名を表示（オレンジ色）
// v2.0.0 - 2026-03-11 イベントID動的切替・時間自由入力・会場テキスト入力に対応
//                      timesフィールド追加（管理画面復元用）
//                      yoshino_events/{EID}.slotsを保存時に自動更新
// v1.0.0 - 2026-03-09 初版（EID=yoshino固定・スロットチェックボックス・会場ラジオ）

if (!function_exists('yoshino_calendar_page')):
function yoshino_calendar_page() {
    yoshino_common_css();
    $fb = yoshino_firebase_config();
    ?>
<div class="ye-wrap">
<h1>📅 公演日カレンダー</h1>
<p style="color:#666;font-size:13px;margin-bottom:16px">グレーの日をクリック→公演日に。青い日をクリック→設定を編集。保存を忘れずに。</p>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;padding:12px 16px;background:#e8f5e9;border-radius:6px;border:1px solid #a5d6a7">
    <label style="font-size:13px;font-weight:bold;color:#2e7d32">イベント：</label>
    <select id="yc-eid-select" style="padding:6px 10px;border:1px solid #a5d6a7;border-radius:4px;font-size:13px;font-family:inherit;background:#fff">
        <option value="yoshino">yoshino（脱走兵と群衆）</option>
        <option value="hajimete">hajimete（始めて続ける）</option>
        <option value="__other__">その他（直接入力）↓</option>
    </select>
    <input type="text" id="yc-eid-input" placeholder="event_id を入力"
        style="display:none;padding:6px 10px;border:1px solid #a5d6a7;border-radius:4px;font-size:13px;font-family:inherit;width:180px;background:#fff">
    <button id="yc-eid-apply" style="padding:6px 16px;background:#388e3c;color:#fff;border:none;border-radius:4px;font-size:13px;cursor:pointer;font-family:inherit;font-weight:bold">切替</button>
    <span id="yc-eid-current" style="font-size:12px;color:#555"></span>
</div>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
    <button id="yc-prev" style="padding:6px 14px;cursor:pointer">◀ 前月</button>
    <span id="yc-month" style="font-size:18px;font-weight:bold;min-width:120px;text-align:center"></span>
    <button id="yc-next" style="padding:6px 14px;cursor:pointer">翌月 ▶</button>
    <span id="yc-status" style="color:#888;margin-left:16px;font-size:13px"></span>
</div>

<div id="yc-cal" style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;max-width:560px;margin-bottom:20px"></div>

<div id="yc-editor" style="display:none;border:1px solid #90caf9;border-radius:4px;padding:16px 20px;margin-bottom:20px;max-width:560px;background:#e3f2fd">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <strong style="font-size:13px;color:#1565c0"><span id="yc-ed-date"></span> の設定</strong>
        <button id="yc-remove-day" style="background:#888;color:#fff;border:none;border-radius:3px;padding:5px 12px;font-size:12px;cursor:pointer;font-family:inherit">この日を外す</button>
    </div>
    <div style="margin-bottom:12px">
        <label style="display:block;font-size:11px;letter-spacing:.1em;color:#1565c0;margin-bottom:6px;font-weight:500">タイトル</label>
        <input type="text" id="yc-day-title" style="width:100%;border:1px solid #90caf9;border-radius:3px;padding:8px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff">
    </div>
    <div style="margin-bottom:12px">
        <label style="display:block;font-size:11px;letter-spacing:.1em;color:#1565c0;margin-bottom:6px;font-weight:500">会場</label>
        <input type="text" id="yc-day-venue" placeholder="例：MAMEHICO銀座" style="width:100%;border:1px solid #90caf9;border-radius:3px;padding:8px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;background:#fff">
    </div>
    <div style="margin-bottom:12px">
        <label style="display:block;font-size:11px;letter-spacing:.1em;color:#1565c0;margin-bottom:6px;font-weight:500">時間（開始 〜 終了）</label>
        <div id="yc-times-wrap" style="display:flex;flex-direction:column;gap:8px"></div>
        <button id="yc-add-time" style="margin-top:8px;padding:5px 14px;font-size:12px;cursor:pointer;border:1px solid #90caf9;border-radius:3px;background:#fff;color:#1565c0;font-family:inherit">＋ 時間を追加</button>
    </div>
    <div>
        <label style="display:block;font-size:11px;letter-spacing:.1em;color:#1565c0;margin-bottom:6px;font-weight:500">ミュージックチャージ（円）</label>
        <input type="number" id="yc-charge" style="width:160px;border:1px solid #90caf9;border-radius:3px;padding:8px 12px;font-size:13px;font-family:inherit;background:#fff" placeholder="0">
    </div>
</div>

<button id="yc-save" style="padding:10px 32px;background:#1976d2;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;font-weight:bold">この月の設定を保存</button>
</div>

<script type="module">
import{initializeApp,getApps}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import{getFirestore,doc,getDoc,setDoc}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js";
var fb=<?php echo $fb; ?>;
var EID="yoshino";
var app=getApps().find(function(a){return a.name==="yc";})||initializeApp(fb,"yc");
var db=getFirestore(app);
var DAYS=["日","月","火","水","木","金","土"];
var today=new Date(), cy=today.getFullYear(), cm=today.getMonth()+1;
var openDates={}, dayTitles={}, dayVenues={}, dayCharges={}, dayTimes={};
var editDate=null, defaultTitle="", knownSlots=[];

function padym(y,m){return y+String(m).padStart(2,"00");}
function setst(msg,c){var e=document.getElementById("yc-status");e.textContent=msg;e.style.color=c||"#888";}
function timeToKey(t){return t.replace(":","");}

document.getElementById("yc-eid-select").addEventListener("change",function(){
    document.getElementById("yc-eid-input").style.display=
        this.value==="__other__"?"inline-block":"none";
});

document.getElementById("yc-eid-apply").addEventListener("click",function(){
    var sel=document.getElementById("yc-eid-select");
    var inp=document.getElementById("yc-eid-input");
    if(sel.value==="__other__"){
        var v=inp.value.trim();
        if(!v){alert("イベントIDを入力してください");return;}
        EID=v;
    }else{
        EID=sel.value;
    }
    document.getElementById("yc-eid-current").textContent="現在："+EID;
    init();
});

async function init(){
    defaultTitle=""; knownSlots=[];
    try{
        var s=await getDoc(doc(db,"yoshino_events",EID));
        if(s.exists()){
            if(s.data().title) defaultTitle=s.data().title;
            if(s.data().slots) knownSlots=s.data().slots;
        }
    }catch(e){}
    loadMonth(cy,cm);
}

async function loadMonth(y,m){
    openDates={};dayTitles={};dayVenues={};dayCharges={};dayTimes={};
    editDate=null;
    document.getElementById("yc-editor").style.display="none";
    setst("読み込み中...");
    try{
        var s=await getDoc(doc(db,"yoshino_open_dates",EID+"_"+padym(y,m)));
        if(s.exists()){
            var d=s.data();
            (d.dates||[]).forEach(function(dt){openDates[dt]=true;});
            if(d.titles) Object.assign(dayTitles,d.titles);
            if(d.venues) Object.assign(dayVenues,d.venues);
            if(d.charges) Object.assign(dayCharges,d.charges);
            if(d.times){
                Object.assign(dayTimes,d.times);
            } else if(d.slot_overrides){
                Object.keys(d.slot_overrides).forEach(function(dt){
                    var keys=d.slot_overrides[dt];
                    dayTimes[dt]=keys.map(function(k){
                        var found=knownSlots.find(function(s){return s.key===k;});
                        return found
                            ? {time:found.time, end:found.end, key:k}
                            : {time:"", end:"", key:k};
                    });
                });
            }
        }
        setst("読み込み完了","#4caf50");
    }catch(e){setst("エラー: "+e.message,"#e53935");}
    render(y,m);
}

function render(y,m){
    document.getElementById("yc-month").textContent=y+"年 "+m+"月";
    var cal=document.getElementById("yc-cal");
    cal.innerHTML="";
    DAYS.forEach(function(d,i){
        var c=document.createElement("div");
        c.textContent=d;
        c.style.cssText="text-align:center;font-weight:bold;font-size:13px;padding:4px 0;color:"+
            (i===0?"#e53935":i===6?"#1976d2":"#333");
        cal.appendChild(c);
    });
    var first=new Date(y,m-1,1).getDay(), last=new Date(y,m,0).getDate();
    for(var i=0;i<first;i++) cal.appendChild(document.createElement("div"));
    for(var d=1;d<=last;d++){
        var ds=y+"-"+String(m).padStart(2,"0")+"-"+String(d).padStart(2,"0");
        var isOpen=!!openDates[ds], isEd=ds===editDate;
        var c=document.createElement("div");
        var titleSub=dayTitles[ds]
            ? "<span style='font-size:9px;line-height:1.2;display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#1565c0'>"+dayTitles[ds]+"</span>"
            : "";
        var timeSub="";
        if(dayTimes[ds]&&dayTimes[ds].length>0&&dayTimes[ds][0].time){
            timeSub="<span style='font-size:9px;display:block;color:#555'>"+
                dayTimes[ds].map(function(t){return t.time;}).join(" / ")+"</span>";
        }
        var venueSub=dayVenues[ds]
            ? "<span style='font-size:8px;display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#e65100'>"+dayVenues[ds]+"</span>"
            : "";
        c.innerHTML="<span>"+d+"</span>"+titleSub+timeSub+venueSub;
        if(isEd){
            c.style.cssText="text-align:center;padding:6px 2px;border-radius:6px;font-size:14px;border:2px solid #1565c0;cursor:pointer;font-weight:bold;background:#bbdefb;color:#1565c0;min-height:44px";
        }else if(isOpen){
            c.style.cssText="text-align:center;padding:6px 2px;border-radius:6px;font-size:14px;border:1px solid #90caf9;cursor:pointer;font-weight:bold;background:#e3f2fd;color:#1565c0;min-height:44px";
        }else{
            c.style.cssText="text-align:center;padding:6px 2px;border-radius:6px;font-size:14px;border:1px solid #ddd;cursor:pointer;background:#f5f5f5;color:#aaa;min-height:44px";
        }
        (function(dateStr,cell){
            cell.addEventListener("click",function(){
                if(!openDates[dateStr]){
                    openDates[dateStr]=true;
                    editDate=dateStr;
                    showEditor(dateStr);
                }else if(editDate===dateStr){
                    editDate=null;
                    document.getElementById("yc-editor").style.display="none";
                    render(cy,cm);
                }else{
                    editDate=dateStr;
                    showEditor(dateStr);
                }
            });
        })(ds,c);
        cal.appendChild(c);
    }
}

function addTimeRow(ds, startVal, endVal){
    var wrap=document.getElementById("yc-times-wrap");
    var row=document.createElement("div");
    row.style.cssText="display:flex;align-items:center;gap:8px;flex-wrap:wrap";
    row.innerHTML=
        "<input type='time' class='yc-t-start' value='"+startVal+"' "+
        "style='padding:6px 8px;border:1px solid #90caf9;border-radius:3px;font-size:13px;font-family:inherit;background:#fff'>"+
        "<span style='color:#555'>〜</span>"+
        "<input type='time' class='yc-t-end' value='"+endVal+"' "+
        "style='padding:6px 8px;border:1px solid #90caf9;border-radius:3px;font-size:13px;font-family:inherit;background:#fff'>"+
        "<button class='yc-rm-time' style='padding:4px 10px;font-size:12px;cursor:pointer;border:1px solid #ccc;"+
        "border-radius:3px;background:#fff;color:#666;font-family:inherit'>✕</button>";
    row.querySelector(".yc-rm-time").addEventListener("click",function(){
        row.remove();
        syncTimes(ds);
    });
    row.querySelector(".yc-t-start").addEventListener("change",function(){syncTimes(ds);});
    row.querySelector(".yc-t-end").addEventListener("change",function(){syncTimes(ds);});
    wrap.appendChild(row);
}

function buildTimeRows(ds){
    var wrap=document.getElementById("yc-times-wrap");
    wrap.innerHTML="";
    var times=dayTimes[ds]&&dayTimes[ds].length>0
        ? dayTimes[ds]
        : [{time:"",end:""}];
    times.forEach(function(t){
        addTimeRow(ds, t.time||"", t.end||"");
    });
}

function syncTimes(ds){
    var rows=document.getElementById("yc-times-wrap").querySelectorAll("div");
    var times=[];
    rows.forEach(function(row){
        var s=row.querySelector(".yc-t-start").value;
        var e=row.querySelector(".yc-t-end").value;
        if(s) times.push({time:s, end:e, key:timeToKey(s)});
    });
    dayTimes[ds]=times;
    render(cy,cm);
}

function showEditor(ds){
    if(!dayTitles[ds]&&defaultTitle) dayTitles[ds]=defaultTitle;
    document.getElementById("yc-ed-date").textContent=ds;

    var titleEl=document.getElementById("yc-day-title");
    titleEl.value=dayTitles[ds]||"";
    titleEl.oninput=function(){
        var v=this.value.trim();
        if(v) dayTitles[ds]=v; else delete dayTitles[ds];
        render(cy,cm);
    };

    var venueEl=document.getElementById("yc-day-venue");
    venueEl.value=dayVenues[ds]||"";
    venueEl.oninput=function(){
        var v=this.value.trim();
        if(v) dayVenues[ds]=v; else delete dayVenues[ds];
    };

    buildTimeRows(ds);

    document.getElementById("yc-add-time").onclick=function(){
        addTimeRow(ds,"","");
    };

    var chargeEl=document.getElementById("yc-charge");
    chargeEl.value=dayCharges[ds]||"";
    chargeEl.oninput=function(){
        var v=parseInt(this.value)||0;
        if(v>0) dayCharges[ds]=v; else delete dayCharges[ds];
    };

    document.getElementById("yc-editor").style.display="block";
    render(cy,cm);
}

document.getElementById("yc-remove-day").addEventListener("click",function(){
    if(!editDate) return;
    delete openDates[editDate];
    delete dayTitles[editDate];
    delete dayVenues[editDate];
    delete dayCharges[editDate];
    delete dayTimes[editDate];
    editDate=null;
    document.getElementById("yc-editor").style.display="none";
    render(cy,cm);
});

async function save(){
    if(editDate) syncTimes(editDate);
    setst("保存中...");
    try{
        var dates=Object.keys(openDates).filter(function(d){return openDates[d];}).sort();
        var ov={}, ti={}, ve={}, ch={}, tm={};

        dates.forEach(function(d){
            if(dayTimes[d]&&dayTimes[d].length>0){
                tm[d]=dayTimes[d];
                ov[d]=dayTimes[d].map(function(t){return t.key;});
            }
        });
        Object.keys(dayTitles).forEach(function(d){if(openDates[d]&&dayTitles[d])ti[d]=dayTitles[d];});
        Object.keys(dayVenues).forEach(function(d){if(openDates[d]&&dayVenues[d])ve[d]=dayVenues[d];});
        Object.keys(dayCharges).forEach(function(d){if(openDates[d]&&dayCharges[d])ch[d]=dayCharges[d];});

        await setDoc(
            doc(db,"yoshino_open_dates",EID+"_"+padym(cy,cm)),
            {dates:dates, slot_overrides:ov, titles:ti, venues:ve, charges:ch, times:tm},
            {merge:false}
        );

        await updateEventSlots();

        setst("保存完了 ✓","#4caf50");
    }catch(e){setst("保存エラー: "+e.message,"#e53935");}
}

async function updateEventSlots(){
    var newSlots={};
    knownSlots.forEach(function(s){newSlots[s.key]=s;});
    Object.keys(dayTimes).forEach(function(d){
        (dayTimes[d]||[]).forEach(function(t){
            if(t.key&&t.time&&!newSlots[t.key]){
                newSlots[t.key]={time:t.time, end:t.end, key:t.key};
            }
        });
    });
    var merged=Object.keys(newSlots).sort().map(function(k){return newSlots[k];});
    if(merged.length!==knownSlots.length){
        await setDoc(doc(db,"yoshino_events",EID),{slots:merged},{merge:true});
        knownSlots=merged;
    }
}

document.getElementById("yc-prev").addEventListener("click",function(){
    cm--;if(cm<1){cm=12;cy--;}loadMonth(cy,cm);
});
document.getElementById("yc-next").addEventListener("click",function(){
    cm++;if(cm>12){cm=1;cy++;}loadMonth(cy,cm);
});
document.getElementById("yc-save").addEventListener("click",save);

document.getElementById("yc-eid-current").textContent="現在："+EID;
init();
</script>
    <?php
}
endif;
