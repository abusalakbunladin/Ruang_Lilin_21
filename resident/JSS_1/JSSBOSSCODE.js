function opponentCardPool(){
const extra = ENEMY_EXCLUSIVE[stageIndex];
return extra ? CARD_POOL.concat(extra) : CARD_POOL;
}
function trumpsPerRound(){
if(stageIndex<=2) return 1;
if(stageIndex===3) return 2;
return 3;
}
function isDesperatePhase(){
return stageIndex === STAGES.length-1 && S.lives.opponent <= 1 && S.lives.opponent > 0;
}
function computeSkill(){
if(isDesperatePhase()) return 0.99;
const diff = DIFFICULTIES[difficulty];
const stage = STAGES[stageIndex];
const enrage = diff.enrage * (S.opponent.stageLossCount||0);
return Math.min(0.95, Math.max(0.15, diff.baseSkill + stage.stageBonus + enrage));
}
function announceDesperatePhase(){
const el = document.getElementById('card-announce');
const iconEl = document.getElementById('announce-icon');
const tagEl = document.getElementById('announce-tag');
const nameEl = document.getElementById('announce-name');
iconEl.innerHTML = iconSvg('royalEye');
tagEl.textContent = 'RAJA IBLIS';
nameEl.textContent = 'DI UJUNG TANDUK!';
el.classList.remove('hidden','show','exclusive');
void el.offsetWidth;
el.classList.add('exclusive','show');
flashFx('doublebust');
shakeTable();
sfxExclusiveCard();
setTimeout(()=>{ el.classList.add('hidden'); el.classList.remove('show'); }, 1800);
log('sys', '<b>Raja Iblis mendesis marah</b> &mdash; lilinnya nyaris padam, dan ia tak lagi peduli aturan permainan. Bersiaplah untuk segalanya.');
}
function aiChooseCard(specials, skill){
const room = S.target - trueTotal('opponent');
const tableFull = (S.opponent.tableSlotsUsed||0) >= TABLE_LIMIT;
const weighted = [];
const add = (id, w) => {
if(w<=0) return;
const c = specials.find(c=>c.id===id);
if(!c) return;
if(PERSISTENT.has(id) && tableFull) return;
weighted.push([c,w]);
};
const knownGone = new Set();
knownGone.add(S.opponent.hidden);
S.opponent.visible.forEach(v=>knownGone.add(v));
S.player.visible.forEach(v=>knownGone.add(v));
const bustCountNow = S.deck.filter(v => v > room).length;
const bustRiskNow = S.deck.length>0 ? bustCountNow/S.deck.length : 0;
add('perfect', room>2 && bustRiskNow<0.6 ? 1.6 : 0.2);
add('draw3', (room>=3 && !knownGone.has(3)) ? 1.0 : 0);
add('draw5', (room>=5 && !knownGone.has(5)) ? 1.0 : 0);
add('draw7', (room>=7 && !knownGone.has(7)) ? 1.0 : 0);
add('draw9', (room>=9 && !knownGone.has(9)) ? 1.0 : 0);
add('return', room<0 ? 2.2 : (bustRiskNow>0.55 ? 1.4*(0.5+skill) : 0.1));
add('exchange', 0.8);
add('destroy', 1.1 * (0.6+skill));
add('remove', 0.9 * (0.6+skill));
add('shield', S.opponent.shieldActive ? 0 : 1.0 * (0.6+skill));
add('oneUp', 0.9);
add('desperation', S.opponent.noLossIfLose ? 0 : 0.7);
add('goFor17', trueTotal('opponent')<=17 && trueTotal('player')>17 ? 1.3 : 0.4);
add('goFor24', 0.6);
add('goFor27', 0.5);
add('loveEnemy', 0.4);
add('trumpSwitch', specials.length>=6 ? 0.9 : 0.3);
add('deputyCurse', 1.3);
add('demonGrip', 1.1);
add('kingsWill', room<-3 ? 1.0 : 0.3);
add('eternalSilence', S.player.silenced ? 0 : 1.2);
if(weighted.length===0) return null;
const total = weighted.reduce((s,[,w])=>s+w,0);
let r = Math.random()*total;
for(const [c,w] of weighted){ r -= w; if(r<=0) return c; }
return weighted[weighted.length-1][0];
}
function aiTurn(){
if(S.gameOver || S.awaitingContinue) return;
const _sidAi = sid;
const skill = computeSkill();
let attempts = 0;
const maxAttempts = 3;
while(S.opponent.specials.length>0 && attempts<maxAttempts){
const playChance = skill*0.5*Math.pow(0.5, attempts);
if(Math.random() >= playChance) break;
attempts++;
const choice = aiChooseCard(S.opponent.specials, skill);
if(!choice) break;
const idx = S.opponent.specials.findIndex(c=>c.uid===choice.uid);
S.opponent.specials.splice(idx,1);
const wasKingsWill = choice.id==='kingsWill';
applySpecial('opponent', choice);
render();
if(wasKingsWill) return; 
}
setTimeout(()=>{
if(_sidAi!==sid || S.gameOver || S.turn!=='opponent' || S.awaitingContinue) return;
const total = trueTotal('opponent');
const gap = S.target - total;
let willHit;
if(total > S.target || gap <= 0){
willHit = false;
} else {
const bustCount = S.deck.filter(v => v > gap).length;
const bustRisk = S.deck.length>0 ? bustCount/S.deck.length : 0;
const riskAversion = 0.35 + skill*0.45; 
let hitProb = 0.55 + (gap/15) - bustRisk*riskAversion;
hitProb = Math.max(0.05, Math.min(0.92, hitProb));
willHit = Math.random() < hitProb;
}
if(willHit) doHit('opponent');
else doStand('opponent');
}, 700);
}