<?php
// 銀座ランチ管理 ② カレンダー
// v3.0.2 - 2026-03-10

if (!function_exists('mamehico_ginza_render_calendar')):
function mamehico_ginza_render_calendar() {
    $firebase_config_ginza = json_encode([
        'apiKey'            => 'AIzaSyDnDy4ipNbMbzXd2yurCgVwKjyEkp3FCZE',
        'authDomain'        => 'mamehico-schedule.firebaseapp.com',
        'projectId'         => 'mamehico-schedule',
        'storageBucket'     => 'mamehico-schedule.firebasestorage.app',
        'messagingSenderId' => '992467894050',
        'appId'             => '1:992467894050:web:88b676e753523dac572f7b',
    ]);
    echo mamehico_ginza_admin_styles();
    echo '<div class="mm-admin-wrap" id="mamehico-admin-wrap">
<h1>🗓 銀座ランチ 営業日設定</h1>
<p class="mm-desc">赤い日付＝休業日。クリックでON/OFFを切り替え、月ごとに「保存」を押してください。対象曜日：火・水・木・金</p>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <button id="cal-prev" style="padding:5px 14px;cursor:pointer;border:1px solid #ddd;border-radius:3px;background:#fff">◀ 前月</button>
    <button id="cal-next" style="padding:5px 14px;cursor:pointer;border:1px solid #ddd;border-radius:3px;background:#fff">翌月 ▶</button>
    <span style="display:inline-block;width:14px;height:14px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:3px"></span> 営業日 &nbsp;
    <span style="display:inline-block;width:14px;height:14px;background:#ffebee;border:1px solid #ef9a9a;border-radius:3px"></span> 休業日 &nbsp;
    <span style="display:inline-block;width:14px;height:14px;background:#f5f5f5;border:1px solid #ccc;border-radius:3px"></span> 対象外
    <span id="mamehico-status" style="color:#888;margin-left:8px;font-size:13px"></span>
</div>
<div style="display:flex;gap:40px;flex-wrap:wrap">
    <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;min-width:280px">
            <span id="cal-label-0" style="font-size:15px;font-weight:bold;color:#2E303E"></span>
            <button id="cal-save-0" style="padding:5px 16px;background:#2E303E;color:#fff;border:none;border-radius:4px;font-size:12px;cursor:pointer">保存</button>
        </div>
        <div id="cal-grid-0" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;width:280px"></div>
    </div>
    <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;min-width:280px">
            <span id="cal-label-1" style="font-size:15px;font-weight:bold;color:#2E303E"></span>
            <button id="cal-save-1" style="padding:5px 16px;background:#2E303E;color:#fff;border:none;border-radius:4px;font-size:12px;cursor:pointer">保存</button>
        </div>
        <div id="cal-grid-1" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;width:280px"></div>
    </div>
</div>
</div>

<script type="module">
import{initializeApp}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import{getFirestore,doc,getDoc,setDoc}from"https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js";
var fb=' . $firebase_config_ginza . ';
var app=initializeApp(fb,"mamehico-ginza-admin");
var db=getFirestore(app);
var DOW=[2,3,4,5];
var JP=["日","月","火","水","木","金","土"];
var t=new Date();
var baseY=t.getFullYear(),baseM=t.getMonth()+1;
var months=[{y:0,m:0,closed:new Set()},{y:0,m:0,closed:new Set()}];
function ym(y,m){return y+String(m).padStart(2,"0");}
function st(msg,col){var e=document.getElementById("mamehico-status");if(e){e.textContent=msg;e.style.color=col||"#888";}}
function addMonth(y,m,delta){m+=delta;if(m>12){m=1;y++;}if(m<1){m=12;y--;}return{y:y,m:m};}
async function loadMonth(idx){
  var mo=months[idx];mo.closed.clear();
  try{var r=await getDoc(doc(db,"ginza_closed_dates",ym(mo.y,mo.m)));if(r.exists()&&r.data().dates)r.data().dates.forEach(function(d){mo.closed.add(d);})}
  catch(e){st("読み込みエラー: "+e.message,"#e53935");}
  renderMonth(idx);
}
function renderMonth(idx){
  var mo=months[idx];
  document.getElementById("cal-label-"+idx).textContent=mo.y+"年 "+mo.m+"月";
  var grid=document.getElementById("cal-grid-"+idx);
  grid.innerHTML="";
  JP.forEach(function(d,i){var c=document.createElement("div");c.textContent=d;c.style.cssText="text-align:center;font-weight:bold;font-size:11px;padding:3px 0;color:"+(i===0?"#e53935":i===6?"#1976d2":"#666");grid.appendChild(c);});
  var fd=new Date(mo.y,mo.m-1,1).getDay(),ld=new Date(mo.y,mo.m,0).getDate();
  for(var i=0;i<fd;i++)grid.appendChild(document.createElement("div"));
  for(var d=1;d<=ld;d++){
    var dow=new Date(mo.y,mo.m-1,d).getDay();
    var ds=mo.y+"-"+String(mo.m).padStart(2,"0")+"-"+String(d).padStart(2,"0");
    var it=DOW.indexOf(dow)!==-1,ic=mo.closed.has(ds);
    var c=document.createElement("div");
    c.textContent=d;
    c.style.cssText="text-align:center;padding:7px 2px;border-radius:4px;font-size:13px;border:1px solid;";
    if(!it){c.style.background="#f5f5f5";c.style.borderColor="#eee";c.style.color="#ccc";}
    else if(ic){c.style.background="#ffebee";c.style.borderColor="#ef9a9a";c.style.color="#c62828";c.style.cursor="pointer";c.style.fontWeight="bold";}
    else{c.style.background="#e8f5e9";c.style.borderColor="#a5d6a7";c.style.color="#2e7d32";c.style.cursor="pointer";}
    if(it){(function(s,el,ix){el.addEventListener("click",function(){if(months[ix].closed.has(s))months[ix].closed.delete(s);else months[ix].closed.add(s);renderMonth(ix);});})(ds,c,idx);}
    grid.appendChild(c);
  }
}
async function saveMonth(idx){
  var mo=months[idx];st("保存中...","#888");
  try{await setDoc(doc(db,"ginza_closed_dates",ym(mo.y,mo.m)),{dates:Array.from(mo.closed).sort()},{merge:false});st(mo.m+"月の設定を保存しました ✓","#4caf50");}
  catch(e){st("保存エラー: "+e.message,"#e53935");}
}
function navigate(delta){
  var nb=addMonth(baseY,baseM,delta);baseY=nb.y;baseM=nb.m;
  var n1=addMonth(baseY,baseM,1);
  months[0].y=baseY;months[0].m=baseM;months[0].closed.clear();
  months[1].y=n1.y;months[1].m=n1.m;months[1].closed.clear();
  st("読み込み中...","#888");
  Promise.all([loadMonth(0),loadMonth(1)]).then(function(){st("","");});
}
document.getElementById("cal-prev").addEventListener("click",function(){navigate(-1);});
document.getElementById("cal-next").addEventListener("click",function(){navigate(1);});
document.getElementById("cal-save-0").addEventListener("click",function(){saveMonth(0);});
document.getElementById("cal-save-1").addEventListener("click",function(){saveMonth(1);});
var n1=addMonth(baseY,baseM,1);
months[0].y=baseY;months[0].m=baseM;months[1].y=n1.y;months[1].m=n1.m;
Promise.all([loadMonth(0),loadMonth(1)]).then(function(){st("読み込み完了","#4caf50");});
</script>';
}
endif;
