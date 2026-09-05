function lum(c){const m=c.match(/rgba?\((\d+), ?(\d+), ?(\d+)(?:, ?([\d.]+))?\)/);if(!m)return null;const a=m[4]===undefined?1:parseFloat(m[4]);if(a<0.5)return null;return (0.2126*m[1]+0.7152*m[2]+0.0722*m[3])/255;}
const xau=[];
document.querySelectorAll('body *').forEach(el=>{
  if(el.matches('img, svg, svg *, picture, video, canvas'))return;
  const cs=getComputedStyle(el);
  if(cs.display==='none'||cs.visibility==='hidden')return;
  const r=el.getBoundingClientRect();
  if(r.width<24||r.height<12)return;
  const L=lum(cs.backgroundColor);
  if(L===null||L<0.75)return;
  xau.push({lop:(el.className.baseVal??el.className??'').toString().slice(0,50),nen:cs.backgroundColor,chu:cs.color,chu5:(el.textContent||'').trim().slice(0,25)});
});
JSON.stringify({url:location.pathname, scheme:document.documentElement.dataset.scheme, soLoi:xau.length, xau:xau.slice(0,6)});
