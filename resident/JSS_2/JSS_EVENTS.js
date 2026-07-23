document.addEventListener('keydown', (e)=>{
if(e.key && e.key.length===1 && /[a-zA-Z]/.test(e.key)){
godKeyBuffer = (godKeyBuffer + e.key.toLowerCase()).slice(-GOD_CODE_LEN);
if(godKeyBuffer.length===GOD_CODE_LEN && simpleHash(godKeyBuffer)===GOD_CODE_HASH){
godKeyBuffer = '';
toggleGodMode();
}
}
});
document.addEventListener('DOMContentLoaded', ()=>{
document.getElementById('god-immune').addEventListener('change', (e)=>{
godModeImmune = e.target.checked;
});
document.getElementById('god-win-round').addEventListener('click', ()=>godInstantRoundResult('player'));
document.getElementById('god-lose-round').addEventListener('click', ()=>godInstantRoundResult('opponent'));
document.getElementById('god-force-win').addEventListener('click', ()=>godForceGameEnd('win'));
document.getElementById('god-force-lose').addEventListener('click', ()=>godForceGameEnd('lose'));
document.querySelectorAll('.god-stage-btns [data-stage]').forEach(btn=>{
btn.addEventListener('click', ()=>godJumpToStage(parseInt(btn.getAttribute('data-stage'))));
});
document.getElementById('god-close').addEventListener('click', toggleGodMode);
});
document.addEventListener('DOMContentLoaded', ()=>{
document.getElementById('btn-continue').addEventListener('click', ()=>{
document.getElementById('overlay-round').classList.add('hidden');
S.awaitingContinue = false;
if(S.lives.player<=0){
endGame('lose');
return;
}
if(S.lives.opponent<=0){
if(stageIndex >= STAGES.length-1){
endGame('win');
} else {
carriedPlayerLife = S.lives.player;
stageIndex += 1;
if(stageIndex === STAGES.length-1){
playDoorTransition(2900, ()=>showStageTransition(true));
} else {
showStageTransition(true);
}
}
return;
}
startRound(false);
});
document.getElementById('btn-stage-continue').addEventListener('click', ()=>{
document.getElementById('overlay-stage').classList.add('hidden');
initStageMatch();
});
});
window.addEventListener('DOMContentLoaded', ()=>{
document.querySelector('.tutor-figure-wrap').innerHTML=window.SPRITE_TUTOR;
document.getElementById('end-badge').innerHTML=window.SPRITE_BADGE;
document.getElementById('candle-opp').outerHTML=window.SPRITE_CANDLE_OPP;
document.getElementById('candle-player').outerHTML=window.SPRITE_CANDLE_PLAYER;
initialCandlePositions();
let selectedDifficulty = 'normal';
const diffDescs = {
mudah:'Mode Bayi: lawan bermain santai dan konsisten, dan nyawamu selalu pulih penuh tiap naik ke tahap berikutnya.',
normal:'Normal: lawan makin licik tiap kali kalah ronde darimu. Nyawamu cuma pulih 1 nyala tiap naik tahap -- luka lama tetap membekas.',
sulit:'Sulit: lawan makin nekat & berbahaya tiap kekalahannya, dan nyawamu TIDAK pulih sama sekali antar tahap -- bawa semua lukamu sampai akhir.'
};
document.querySelectorAll('.diff-btn').forEach(btn=>{
btn.addEventListener('click', ()=>{
document.querySelectorAll('.diff-btn').forEach(b=>b.classList.remove('selected'));
btn.classList.add('selected');
selectedDifficulty = btn.getAttribute('data-diff');
document.getElementById('diff-desc').textContent = diffDescs[selectedDifficulty];
});
});
document.getElementById('btn-start').addEventListener('click', ()=>{
ensureAudio();
if(!tutorialSeenBefore()){
startTutorial(selectedDifficulty);
} else {
document.getElementById('screen-start').classList.remove('active');
document.getElementById('screen-game').classList.add('active');
beginCampaign(selectedDifficulty);
}
});
document.getElementById('btn-tutor-next').addEventListener('click', ()=>{
if(tutorStepIdx < TUTORIAL_STEPS.length-1){
tutorStepIdx += 1;
renderTutorStep();
} else {
finishTutorial();
}
});
document.getElementById('btn-tutor-skip').addEventListener('click', ()=>{
finishTutorial();
});
document.getElementById('link-replay-tutorial').addEventListener('click', (e)=>{
e.preventDefault();
startTutorial(selectedDifficulty);
});
document.getElementById('btn-hit').addEventListener('click', ()=>{
retryMusicIfStalled();
if(!S || S.turn!=='player' || S.awaitingContinue) return;
doHit('player');
});
document.getElementById('btn-stand').addEventListener('click', ()=>{
retryMusicIfStalled();
if(!S || S.turn!=='player' || S.awaitingContinue) return;
doStand('player');
});
document.getElementById('btn-info').addEventListener('click', ()=>{
document.getElementById('overlay-rules').classList.remove('hidden');
});
document.getElementById('btn-close-rules').addEventListener('click', ()=>{
document.getElementById('overlay-rules').classList.add('hidden');
});
document.getElementById('btn-mute').addEventListener('click', (e)=>{
audioMuted = !audioMuted;
e.target.innerHTML = audioMuted ? '&#128263;' : '&#128266;';
refreshMusicVolume();
if(!audioMuted) retryMusicIfStalled();
});
document.getElementById('btn-restart').addEventListener('click', ()=>{
if(S) S.gameOver = true;
document.getElementById('screen-end').classList.remove('active');
document.getElementById('screen-start').classList.add('active');
currentTrack = null;
try{ document.getElementById('bgm-nonboss').pause(); }catch(e){}
try{ document.getElementById('bgm-boss').pause(); }catch(e){}
});
document.getElementById('btn-gallery').addEventListener('click', ()=>{
document.getElementById('screen-end').classList.remove('active');
document.getElementById('screen-gallery').classList.add('active');
renderGalleryTab('numbers');
});
document.getElementById('btn-gallery-back').addEventListener('click', ()=>{
document.getElementById('screen-gallery').classList.remove('active');
document.getElementById('screen-end').classList.add('active');
});
document.querySelectorAll('.gallery-tab').forEach(tab=>{
tab.addEventListener('click', ()=>{
document.querySelectorAll('.gallery-tab').forEach(t=>t.classList.remove('active'));
tab.classList.add('active');
renderGalleryTab(tab.getAttribute('data-tab'));
});
});
document.addEventListener('click', ()=>{
document.querySelectorAll('.trump-chip.tooltip-open').forEach(el=>el.classList.remove('tooltip-open'));
});
});