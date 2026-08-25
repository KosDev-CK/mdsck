/* ============================================================
   Generador de Códigos QR — lógica de la aplicación
   ============================================================ */
(function(){
  'use strict';

  /* ---------------- Estado global ---------------- */
  const state = {
    type: 'url',
    fields: {
      url: { value: 'https://landit.com.mx' },
      text: { value: '' },
      email: { to:'', subject:'', body:'' },
      tel: { value:'' },
      sms: { phone:'', message:'' },
      whatsapp: { phone:'', message:'' },
      wifi: { ssid:'', password:'', enc:'WPA', hidden:false },
      vcard: { name:'', org:'', title:'', phone:'', email:'', website:'' },
      event: { title:'', location:'', desc:'', start:'', end:'' },
      image: { url:'' },
      video: { url:'' },
      social: { platform:'instagram', username:'' },
      pdf: { url:'' },
      spotify: { url:'' }
    },
    frame: { style:'none', text:'ESCANÉAME', color:'#146356', textColor:'#FFFFFF' },
    shape: {
      dotStyle:'square',
      eyeBorderStyle:'square', eyeCenterStyle:'square',
      fgColor:'#14181A', gradient:false, fgColor2:'#146356', gradientAngle:45,
      eyeBorderColor:'#14181A', eyeCenterColor:'#14181A',
      bgColor:'#FFFFFF', transparentBg:false,
      quietZone:4
    },
    logo: { dataUrl:null, sizePct:22, margin:true, removeBg:true },
    ecLevel: 'M',
  };

  const MODULE = 10;        // unidades SVG por módulo
  // La zona de silencio (margen) ahora es configurable vía state.shape.quietZone

  const DEFAULTS = {
    frame: { style:'none', text:'ESCANÉAME', color:'#146356', textColor:'#FFFFFF' },
    shape: {
      dotStyle:'square',
      eyeBorderStyle:'square', eyeCenterStyle:'square',
      fgColor:'#14181A', gradient:false, fgColor2:'#146356', gradientAngle:45,
      eyeBorderColor:'#14181A', eyeCenterColor:'#14181A',
      bgColor:'#FFFFFF', transparentBg:false,
      quietZone:4
    },
    logo: { dataUrl:null, sizePct:22, margin:true, removeBg:true },
    ecLevel: 'M',
  };
  function resetFrame(){ state.frame = JSON.parse(JSON.stringify(DEFAULTS.frame)); renderDesignPane(); renderPreview(); }
  function resetShape(){ state.shape = JSON.parse(JSON.stringify(DEFAULTS.shape)); renderDesignPane(); renderPreview(); }
  function resetLogo(){ state.logo = JSON.parse(JSON.stringify(DEFAULTS.logo)); renderDesignPane(); renderPreview(); }
  function resetLevel(){ state.ecLevel = DEFAULTS.ecLevel; renderDesignPane(); renderPreview(); }
  function resetAllDesign(){
    state.frame = JSON.parse(JSON.stringify(DEFAULTS.frame));
    state.shape = JSON.parse(JSON.stringify(DEFAULTS.shape));
    state.logo = JSON.parse(JSON.stringify(DEFAULTS.logo));
    state.ecLevel = DEFAULTS.ecLevel;
    renderDesignPane(); renderPreview();
  }

  /* ---------------- Utilidades ---------------- */
  function esc(str){
    return String(str||'').replace(/\\/g,'\\\\').replace(/;/g,'\\;').replace(/,/g,'\\,').replace(/:/g,'\\:');
  }
  function encURI(s){ return encodeURIComponent(s||''); }
  function digitsOnly(s){ return String(s||'').replace(/[^\d+]/g,''); }
  function pad2(n){ return String(n).padStart(2,'0'); }
  function icsDate(dateStr, timeStr){
    if(!dateStr) return '';
    const d = dateStr.replace(/-/g,'');
    if(timeStr){ return d + 'T' + timeStr.replace(':','') + '00'; }
    return d;
  }

  /* ---------------- Construcción del payload ---------------- */
  function buildPayload(){
    const f = state.fields;
    switch(state.type){
      case 'url': {
        let v = f.url.value.trim();
        if(v && !/^https?:\/\//i.test(v)) v = 'https://' + v;
        return v;
      }
      case 'text':
        return f.text.value;
      case 'email': {
        const parts = [];
        if(f.email.subject) parts.push('subject=' + encURI(f.email.subject));
        if(f.email.body) parts.push('body=' + encURI(f.email.body));
        return 'mailto:' + f.email.to.trim() + (parts.length ? '?' + parts.join('&') : '');
      }
      case 'tel':
        return 'tel:' + digitsOnly(f.tel.value);
      case 'sms':
        return 'SMSTO:' + digitsOnly(f.sms.phone) + ':' + f.sms.message;
      case 'whatsapp': {
        const raw = f.whatsapp.phone.trim();
        if(!raw) return '';
        // Ya es un enlace de WhatsApp (wa.me / whatsapp.com) o cualquier URL completa: se usa tal cual.
        if(/wa\.me|whatsapp\.com/i.test(raw) || /^https?:\/\//i.test(raw)){
          let link = raw;
          if(!/^https?:\/\//i.test(link)) link = 'https://' + link;
          if(f.whatsapp.message && !link.includes('?')) link += '?text=' + encURI(f.whatsapp.message);
          return link;
        }
        // Código de contacto fijo de WhatsApp (enlace tipo wa.me/qr/CÓDIGO).
        if(/[a-zA-Z]/.test(raw)){
          const codeLink = 'https://wa.me/qr/' + raw.replace(/\s+/g,'');
          return codeLink + (f.whatsapp.message ? '?text=' + encURI(f.whatsapp.message) : '');
        }
        // Número de teléfono normal, con código de país.
        const num = digitsOnly(raw).replace(/^\+/,'');
        return 'https://wa.me/' + num + (f.whatsapp.message ? '?text=' + encURI(f.whatsapp.message) : '');
      }
      case 'wifi':
        return 'WIFI:T:' + (f.wifi.enc||'WPA') + ';S:' + esc(f.wifi.ssid) + ';P:' + esc(f.wifi.password) + ';H:' + (f.wifi.hidden?'true':'false') + ';;';
      case 'vcard': {
        const v = f.vcard;
        return ['BEGIN:VCARD','VERSION:3.0',
          'N:' + esc(v.name), 'FN:' + esc(v.name),
          v.org ? 'ORG:' + esc(v.org) : '',
          v.title ? 'TITLE:' + esc(v.title) : '',
          v.phone ? 'TEL;TYPE=CELL:' + v.phone : '',
          v.email ? 'EMAIL:' + v.email : '',
          v.website ? 'URL:' + v.website : '',
          'END:VCARD'
        ].filter(Boolean).join('\n');
      }
      case 'event': {
        const v = f.event;
        return ['BEGIN:VCALENDAR','VERSION:2.0','BEGIN:VEVENT',
          'SUMMARY:' + esc(v.title),
          v.location ? 'LOCATION:' + esc(v.location) : '',
          v.desc ? 'DESCRIPTION:' + esc(v.desc) : '',
          v.start ? 'DTSTART:' + icsDate(v.start.split('T')[0], (v.start.split('T')[1]||'')) : '',
          v.end ? 'DTEND:' + icsDate(v.end.split('T')[0], (v.end.split('T')[1]||'')) : '',
          'END:VEVENT','END:VCALENDAR'
        ].filter(Boolean).join('\n');
      }
      case 'image': {
        let v = f.image.url.trim();
        if(v && !/^https?:\/\//i.test(v)) v = 'https://' + v;
        return v;
      }
      case 'video': {
        let v = f.video.url.trim();
        if(v && !/^https?:\/\//i.test(v)) v = 'https://' + v;
        return v;
      }
      case 'pdf': {
        let v = f.pdf.url.trim();
        if(v && !/^https?:\/\//i.test(v)) v = 'https://' + v;
        return v;
      }
      case 'social': {
        const u = f.social.username.trim().replace(/^@/, '');
        const bases = {
          instagram: 'https://instagram.com/',
          facebook: 'https://facebook.com/',
          tiktok: 'https://tiktok.com/@',
          x: 'https://x.com/',
          linkedin: 'https://linkedin.com/in/',
          youtube: 'https://youtube.com/@',
          threads: 'https://threads.net/@',
        };
        return u ? (bases[f.social.platform] || bases.instagram) + u : '';
      }
      case 'spotify': {
        let v = f.spotify.url.trim();
        const uriMatch = v.match(/^spotify:(track|album|artist|playlist|show|episode):([A-Za-z0-9]+)/);
        if(uriMatch) return `https://open.spotify.com/${uriMatch[1]}/${uriMatch[2]}`;
        if(v && !/^https?:\/\//i.test(v)) v = 'https://' + v;
        return v;
      }
      default: return '';
    }
  }

  /* ---------------- Generación de la matriz QR ---------------- */
  // Si el contenido es muy largo para el nivel de corrección elegido, se prueba
  // con niveles de menor redundancia (mayor capacidad de datos), en ese orden.
  const EC_CHAINS = { L:['L'], M:['M','L'], Q:['Q','M','L'], H:['H','Q','M','L'] };

  function matrixFromLevelChain(payload, requested){
    const text = payload && payload.length ? payload : ' ';
    const attempts = EC_CHAINS[requested] || EC_CHAINS.M;
    let qr, lastErr;
    for(const level of attempts){
      try{
        qr = qrcode(0, level);
        qr.addData(text);
        qr.make();
        lastErr = null;
        break;
      }catch(e){ lastErr = e; }
    }
    if(lastErr) throw lastErr;
    const count = qr.getModuleCount();
    const isDark = (r,c) => {
      if(r<0||c<0||r>=count||c>=count) return false;
      return qr.isDark(r,c);
    };
    return { count, isDark };
  }

  function getMatrix(payload, wantHigh){
    const requested = wantHigh ? 'H' : state.ecLevel;
    return matrixFromLevelChain(payload, requested);
  }

  // Genera la matriz forzando un nivel específico (para las miniaturas de ejemplo por nivel)
  function getMatrixForLevel(payload, level){
    return matrixFromLevelChain(payload, level);
  }

  /* ---------------- Utilidades de dibujo SVG ---------------- */
  function roundedRectPath(x,y,w,h,tl,tr,br,bl){
    return `M${x+tl},${y}
      H${x+w-tr} A${tr},${tr} 0 0 1 ${x+w},${y+tr}
      V${y+h-br} A${br},${br} 0 0 1 ${x+w-br},${y+h}
      H${x+bl} A${bl},${bl} 0 0 1 ${x},${y+h-bl}
      V${y+tl} A${tl},${tl} 0 0 1 ${x+tl},${y} Z`;
  }

  function isInAnyEye(r,c,count){
    const zones = [[0,0],[0,count-7],[count-7,0]];
    return zones.some(([zr,zc]) => r>=zr && r<zr+7 && c>=zc && c<zc+7);
  }

  /* -------- Formas decorativas reutilizables (módulos y centro de ojo) -------- */
  const DECOR_SHAPES = ['star','diamond','cross','plus','heart','clover'];

  function starPoints(cx,cy,rOuter,rInner,n){
    let pts=[];
    for(let i=0;i<n*2;i++){
      const r = i%2===0 ? rOuter : rInner;
      const a = (Math.PI/n)*i - Math.PI/2;
      pts.push(`${(cx+r*Math.cos(a)).toFixed(2)},${(cy+r*Math.sin(a)).toFixed(2)}`);
    }
    return pts.join(' ');
  }
  function plusPoints(cx,cy,arm,width){
    const a=arm, w=width/2;
    return [
      [cx-w,cy-a],[cx+w,cy-a],[cx+w,cy-w],[cx+a,cy-w],
      [cx+a,cy+w],[cx+w,cy+w],[cx+w,cy+a],[cx-w,cy+a],
      [cx-w,cy+w],[cx-a,cy+w],[cx-a,cy-w],[cx-w,cy-w]
    ].map(p=>p.map(v=>v.toFixed(2)).join(',')).join(' ');
  }
  const HEART_D = 'M12 21s-8-5.2-8-11a4.3 4.3 0 018-2.1 4.3 4.3 0 018 2.1c0 5.8-8 11-8 11z';

  function decorShapeMarkup(shape, x, y, size, fillRef){
    const cx = x+size/2, cy = y+size/2;
    if(shape==='star'){
      return `<polygon points="${starPoints(cx,cy,size*0.5,size*0.2,5)}" fill="${fillRef}"/>`;
    }
    if(shape==='diamond'){
      return `<path d="M${cx},${y+size*0.04} L${x+size*0.96},${cy} L${cx},${y+size*0.96} L${x+size*0.04},${cy} Z" fill="${fillRef}"/>`;
    }
    if(shape==='plus'){
      return `<polygon points="${plusPoints(cx,cy,size*0.46,size*0.32)}" fill="${fillRef}"/>`;
    }
    if(shape==='cross'){
      return `<polygon points="${plusPoints(cx,cy,size*0.46,size*0.32)}" fill="${fillRef}" transform="rotate(45 ${cx} ${cy})"/>`;
    }
    if(shape==='heart'){
      const s = size*0.85/24;
      return `<path d="${HEART_D}" fill="${fillRef}" transform="translate(${x+size*0.075},${y+size*0.03}) scale(${s})"/>`;
    }
    if(shape==='clover'){
      const r = size*0.26, off = size*0.24;
      const dirs = [[0,-off],[off,0],[0,off],[-off,0]];
      return dirs.map(([dx,dy])=>`<circle cx="${cx+dx}" cy="${cy+dy}" r="${r}" fill="${fillRef}"/>`).join('') +
        `<circle cx="${cx}" cy="${cy}" r="${r*0.85}" fill="${fillRef}"/>`;
    }
    return '';
  }

  function buildModulesMarkup(matrix, ox, oy, fillRef){
    fillRef = fillRef || 'url(#qrGrad)';
    const { count, isDark } = matrix;
    const style = state.shape.dotStyle;
    let d = '';
    let extra = '';
    for(let r=0;r<count;r++){
      for(let c=0;c<count;c++){
        if(!isDark(r,c) || isInAnyEye(r,c,count)) continue;
        const x = ox + c*MODULE, y = oy + r*MODULE;
        if(DECOR_SHAPES.includes(style)){
          extra += decorShapeMarkup(style, x, y, MODULE, fillRef);
        } else if(style === 'dots'){
          const cx = x+MODULE/2, cy = y+MODULE/2, rad = MODULE*0.46;
          d += `M${cx-rad},${cy} a${rad},${rad} 0 1 0 ${rad*2},0 a${rad},${rad} 0 1 0 -${rad*2},0 Z `;
        } else if(style === 'rounded'){
          const rad = MODULE*0.32;
          d += roundedRectPath(x,y,MODULE,MODULE,rad,rad,rad,rad) + ' ';
        } else if(style === 'classy'){
          const up = isDark(r-1,c), down = isDark(r+1,c), left = isDark(r,c-1), right = isDark(r,c+1);
          const rad = MODULE*0.5;
          const tl = (!up && !left) ? rad : 0;
          const tr = (!up && !right) ? rad : 0;
          const br = (!down && !right) ? rad : 0;
          const bl = (!down && !left) ? rad : 0;
          d += roundedRectPath(x,y,MODULE,MODULE,tl,tr,br,bl) + ' ';
        } else { // square
          d += `M${x},${y} h${MODULE} v${MODULE} h-${MODULE} Z `;
        }
      }
    }
    return { d, extra };
  }

  function holeFill(){
    return state.shape.transparentBg ? 'transparent' : state.shape.bgColor;
  }

  function eyeCorners(style, size){
    const full = size*0.5, soft = size*0.22;
    switch(style){
      case 'circle': return {tl:full,tr:full,br:full,bl:full};
      case 'rounded': return {tl:soft,tr:soft,br:soft,bl:soft};
      case 'leaf-tl': return {tl:full,tr:0,br:0,bl:0};
      case 'leaf-tr': return {tl:0,tr:full,br:0,bl:0};
      case 'leaf-br': return {tl:0,tr:0,br:full,bl:0};
      case 'leaf-bl': return {tl:0,tr:0,br:0,bl:full};
      case 'diagonal1': return {tl:full,tr:0,br:full,bl:0};
      case 'diagonal2': return {tl:0,tr:full,br:0,bl:full};
      default: return {tl:0,tr:0,br:0,bl:0};
    }
  }

  function buildEyesMarkup(matrix, ox, oy, borderFillRef, centerFillRef){
    borderFillRef = borderFillRef || 'url(#eyeBorderFill)';
    centerFillRef = centerFillRef || 'url(#eyeCenterFill)';
    const { count } = matrix;
    const borderStyle = state.shape.eyeBorderStyle;
    const centerStyle = state.shape.eyeCenterStyle;
    const positions = [ [0,0], [0,count-7], [count-7,0] ];
    let out = '';
    positions.forEach(([r,c]) => {
      const x = ox + c*MODULE, y = oy + r*MODULE;
      const outer = 7*MODULE, innerStart = MODULE, innerSize = 5*MODULE;
      const dotStart = 2*MODULE, dotSize = 3*MODULE;

      const bc = eyeCorners(borderStyle, outer);
      out += `<path d="${roundedRectPath(x,y,outer,outer,bc.tl,bc.tr,bc.br,bc.bl)}" fill="${borderFillRef}" fill-rule="evenodd"/>`;
      out += `<path d="${roundedRectPath(x+innerStart,y+innerStart,innerSize,innerSize,bc.tl*0.7,bc.tr*0.7,bc.br*0.7,bc.bl*0.7)}" fill="${holeFill()}"/>`;

      if(DECOR_SHAPES.includes(centerStyle)){
        out += decorShapeMarkup(centerStyle, x+dotStart, y+dotStart, dotSize, centerFillRef);
      } else {
        const cc = eyeCorners(centerStyle, dotSize);
        out += `<path d="${roundedRectPath(x+dotStart,y+dotStart,dotSize,dotSize,cc.tl,cc.tr,cc.br,cc.bl)}" fill="${centerFillRef}"/>`;
      }
    });
    return out;
  }

  /* ---------------- Marco (frame) ---------------- */
  function frameLayout(style){
    switch(style){
      case 'bottom-rect':    return { top:22, right:22, bottom:78, left:22 };
      case 'bottom-rounded': return { top:22, right:22, bottom:78, left:22 };
      case 'top-text':       return { top:52, right:8, bottom:8, left:8 };
      case 'pill':           return { top:22, right:22, bottom:46, left:22 };
      case 'corners':        return { top:18, right:18, bottom:18, left:18 };
      default:                return { top:0, right:0, bottom:0, left:0 };
    }
  }

  function frameBackgroundMarkup(style, W, H, pad){
    const c = state.frame.color;
    if(style === 'bottom-rect'){
      return `<rect x="0" y="0" width="${W}" height="${H}" rx="4" fill="${c}"/>`;
    }
    if(style === 'bottom-rounded'){
      return `<rect x="0" y="0" width="${W}" height="${H}" rx="26" fill="${c}"/>`;
    }
    if(style === 'pill'){
      return `<rect x="0" y="0" width="${W}" height="${H-pad.bottom+22}" rx="18" fill="none" stroke="${c}" stroke-width="5"/>`;
    }
    if(style === 'corners'){
      const L = 34, s = 5;
      const cornersXY = [[0,0,1,1],[W,0,-1,1],[0,H,1,-1],[W,H,-1,-1]];
      return cornersXY.map(([x,y,dx,dy]) => `
        <path d="M${x+dx*2},${y+dy*L} V${y+dy*2} H${x+dx*L}" fill="none" stroke="${c}" stroke-width="${s}" stroke-linecap="round"/>
      `).join('');
    }
    return '';
  }

  function frameForegroundMarkup(style, W, H, pad){
    const c = state.frame.color, tc = state.frame.textColor, text = (state.frame.text||'').toUpperCase();
    if(style === 'bottom-rect' || style === 'bottom-rounded'){
      return `<text x="${W/2}" y="${H-pad.bottom/2+7}" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="24" fill="${tc}" letter-spacing="1.5">${text}</text>`;
    }
    if(style === 'top-text'){
      return `<text x="${W/2}" y="34" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="24" fill="${c}" letter-spacing="1.5">${text}</text>`;
    }
    if(style === 'pill'){
      const pillW = Math.min(W-40, 26+text.length*15), pillH = 40, px = (W-pillW)/2, py = H-pad.bottom+4;
      return `
        <rect x="${px}" y="${py}" width="${pillW}" height="${pillH}" rx="${pillH/2}" fill="${c}"/>
        <text x="${W/2}" y="${py+pillH/2+7}" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="16" fill="${tc}" letter-spacing="1">${text}</text>
      `;
    }
    return '';
  }

  /* ---------------- Construcción del SVG completo ---------------- */
  function buildSVG(){
    const payload = buildPayload();
    const hasLogo = !!state.logo.dataUrl;
    const matrix = getMatrix(payload, hasLogo);
    const quiet = state.shape.quietZone;
    const qrPix = (matrix.count + quiet*2) * MODULE;

    const pad = frameLayout(state.frame.style);
    const W = qrPix + pad.left + pad.right;
    const H = qrPix + pad.top + pad.bottom;
    const qrOx = pad.left, qrOy = pad.top;
    const modOx = qrOx + quiet*MODULE, modOy = qrOy + quiet*MODULE;

    const bg = state.shape.transparentBg ? 'none' : state.shape.bgColor;
    const gradId = 'qrGrad';
    const fillDef = state.shape.gradient
      ? `<linearGradient id="${gradId}" gradientTransform="rotate(${state.shape.gradientAngle} 0.5 0.5)">
           <stop offset="0%" stop-color="${state.shape.fgColor}"/>
           <stop offset="100%" stop-color="${state.shape.fgColor2}"/>
         </linearGradient>`
      : `<linearGradient id="${gradId}"><stop offset="0%" stop-color="${state.shape.fgColor}"/><stop offset="100%" stop-color="${state.shape.fgColor}"/></linearGradient>`;
    const eyeBorderFillDef = `<linearGradient id="eyeBorderFill"><stop offset="0%" stop-color="${state.shape.eyeBorderColor}"/><stop offset="100%" stop-color="${state.shape.eyeBorderColor}"/></linearGradient>`;
    const eyeCenterFillDef = `<linearGradient id="eyeCenterFill"><stop offset="0%" stop-color="${state.shape.eyeCenterColor}"/><stop offset="100%" stop-color="${state.shape.eyeCenterColor}"/></linearGradient>`;

    const modules = buildModulesMarkup(matrix, modOx, modOy);
    const eyesMarkup = buildEyesMarkup(matrix, modOx, modOy);

    let logoMarkup = '';
    if(hasLogo){
      const sizePx = qrPix * (state.logo.sizePct/100);
      const lx = qrOx + qrPix/2 - sizePx/2, ly = qrOy + qrPix/2 - sizePx/2;
      const holeR = sizePx/2 + (state.logo.margin ? 8 : 0);
      const knockout = state.logo.removeBg
        ? `<circle cx="${qrOx+qrPix/2}" cy="${qrOy+qrPix/2}" r="${holeR}" fill="${state.shape.transparentBg ? '#FFFFFF' : state.shape.bgColor}"/>`
        : '';
      logoMarkup = `
        ${knockout}
        <image x="${lx}" y="${ly}" width="${sizePx}" height="${sizePx}" href="${state.logo.dataUrl}" preserveAspectRatio="xMidYMid meet"/>
      `;
    }

    const frameBg = frameBackgroundMarkup(state.frame.style, W, H, pad);
    const frameFg = frameForegroundMarkup(state.frame.style, W, H, pad);
    const qrBgRect = bg !== 'none' ? `<rect x="${qrOx}" y="${qrOy}" width="${qrPix}" height="${qrPix}" fill="${bg}"/>` : '';

    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}">
        <defs>${fillDef}${eyeBorderFillDef}${eyeCenterFillDef}</defs>
        ${frameBg}
        ${qrBgRect}
        <path d="${modules.d}" fill="url(#${gradId})"/>
        ${modules.extra}
        ${eyesMarkup}
        ${logoMarkup}
        ${frameFg}
      </svg>
    `;
    return { svg, W, H, payload };
  }

  /* ============================================================
     Render de la interfaz
     ============================================================ */
  const CONTENT_TYPES = [
    { id:'url', label:'Enlace', icon:'link' },
    { id:'text', label:'Texto', icon:'text' },
    { id:'email', label:'Email', icon:'mail' },
    { id:'tel', label:'Llamada', icon:'phone' },
    { id:'sms', label:'SMS', icon:'sms' },
    { id:'whatsapp', label:'WhatsApp', icon:'whatsapp' },
    { id:'wifi', label:'Wi-Fi', icon:'wifi' },
    { id:'vcard', label:'V-card', icon:'card' },
    { id:'event', label:'Evento', icon:'event' },
    { id:'image', label:'Imágenes', icon:'image' },
    { id:'video', label:'Vídeo', icon:'video' },
    { id:'social', label:'Redes sociales', icon:'social' },
    { id:'pdf', label:'PDF', icon:'pdf' },
    { id:'spotify', label:'Spotify', icon:'spotify' },
  ];

  const ICONS = {
    link:'<path d="M9 15l6-6M8 12l-2.5 2.5a3.5 3.5 0 105 5L13 17M11 7l2.5-2.5a3.5 3.5 0 115 5L16 12" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
    text:'<path d="M4 6h16M4 12h16M4 18h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
    mail:'<rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="1.6" fill="none"/>',
    phone:'<path d="M6 3.5c1.2 0 2.3.8 2.7 2l.6 1.8c.3.9 0 1.9-.7 2.5l-1 .9a12 12 0 006.2 6.2l.9-1c.6-.7 1.6-1 2.5-.7l1.8.6c1.2.4 2 1.5 2 2.7v1.3c0 1.5-1.3 2.6-2.8 2.4C10.7 21 3 13.3 2.3 5.8 2.1 4.3 3.2 3 4.7 3z" stroke="currentColor" stroke-width="1.5" fill="none"/>',
    sms:'<rect x="3.5" y="5" width="17" height="12" rx="3" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M8 20l2.5-3M7 9.5h10M7 13h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
    whatsapp:'<path d="M12 3a9 9 0 00-7.8 13.5L3 21l4.6-1.2A9 9 0 1012 3z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M8.5 8.8c.2-.6.7-.6 1-.6h.5c.2 0 .5 0 .7.5s.7 1.7.8 1.8.1.3 0 .5-.2.3-.4.5-.3.3-.1.6a6 6 0 002.8 2.5c.3.1.5.1.7-.1s.7-.8.9-1.1.4-.2.6-.1l1.6.8c.2.1.4.2.4.4s0 1.1-.5 1.5-1.5 1-2.9.5A8 8 0 018.6 12c-1-1.4-1-2.6-.9-3z" fill="currentColor"/>',
    wifi:'<path d="M3.5 9.5a13 13 0 0117 0M6.5 12.7a9 9 0 0111 0M9.7 15.8a5 5 0 014.6 0" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/><circle cx="12" cy="19" r="1.2" fill="currentColor"/>',
    card:'<rect x="3" y="5" width="18" height="14" rx="2.2" stroke="currentColor" stroke-width="1.6" fill="none"/><circle cx="8.3" cy="10.6" r="1.8" stroke="currentColor" stroke-width="1.4" fill="none"/><path d="M5.3 15.5c.5-1.6 1.7-2.3 3-2.3s2.5.7 3 2.3M14 10h5M14 13.3h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
    event:'<rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M3.5 9.5h17M8 3.5v3M16 3.5v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
    image:'<rect x="3.5" y="4.5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/><circle cx="8.3" cy="9.3" r="1.6" stroke="currentColor" stroke-width="1.4" fill="none"/><path d="M4 16.5l4.5-4.5 3 3 4-4.5 4.5 5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    video:'<rect x="3" y="5.5" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M16.5 10l4.5-2.8v9.6L16.5 14z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round"/>',
    social:'<circle cx="6" cy="12" r="2.4" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="17.5" cy="6" r="2.4" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="17.5" cy="18" r="2.4" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M8.1 10.9L15.4 7M8.1 13.1l7.3 3.9" stroke="currentColor" stroke-width="1.4"/>',
    spotify:'<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M7 10.2c3-1 7-.7 9.6 1M7.4 13.1c2.5-.7 5.6-.5 7.8.8M7.8 15.8c2-.5 4.4-.4 6 .6" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>',
    png:'<rect x="4" y="4" width="16" height="16" rx="2.5" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="9" cy="9.5" r="1.4" fill="currentColor"/><path d="M4.5 16l4-4 3 3 3.5-4.5 4.5 5.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    svg:'<path d="M6 3h9l4 4v14a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M15 3v4h4" stroke="currentColor" stroke-width="1.5" fill="none"/><text x="7.3" y="17.5" font-size="6.2" font-weight="700" fill="currentColor">SVG</text>',
    pdf:'<path d="M6 3h9l4 4v14a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M15 3v4h4" stroke="currentColor" stroke-width="1.5" fill="none"/><text x="6.6" y="17.5" font-size="6" font-weight="700" fill="currentColor">PDF</text>',
    download:'<path d="M12 4v11m0 0l-4-4m4 4l4-4M5 18h14" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    upload:'<path d="M12 16V6m0 0L8 10m4-4l4 4M5 18h14" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    reset:'<path d="M4 4v5h5M20 20v-5h-5" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 15a7.5 7.5 0 0013.4 2.6M18.5 9a7.5 7.5 0 00-13.4-2.6" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/>',
  };
  function icon(name, size){ return `<svg viewBox="0 0 24 24" width="${size||16}" height="${size||16}">${ICONS[name]||''}</svg>`; }

  /* -------- Vistas previa (swatches) para estilos -------- */
  function dotSwatchSVG(style){
    if(DECOR_SHAPES.includes(style)){
      // patrón disperso de 5 glifos pequeños representativo
      const pos = [[3,3],[13,2],[6,11],[16,13],[10,18]];
      let out = '';
      pos.forEach(([px,py])=>{ out += decorShapeMarkup(style, px, py, 6, '#14181A'); });
      return `<svg viewBox="0 0 24 24">${out}</svg>`;
    }
    // Mini 3x3 patrón representativo (estilos conectados clásicos)
    const on = [[0,0],[1,0],[0,1],[2,1],[1,2],[2,2]];
    let d='';
    on.forEach(([c,r])=>{
      const x=r*8, y=c*8;
      if(style==='dots') d += `<circle cx="${x+4}" cy="${y+4}" r="3.6" fill="#14181A"/>`;
      else if(style==='rounded') d += `<rect x="${x}" y="${y}" width="8" height="8" rx="2.4" fill="#14181A"/>`;
      else if(style==='classy') d += `<rect x="${x}" y="${y}" width="8" height="8" rx="3.6" fill="#14181A"/>`;
      else d += `<rect x="${x}" y="${y}" width="8" height="8" fill="#14181A"/>`;
    });
    return `<svg viewBox="0 0 24 24">${d}</svg>`;
  }
  function eyeBorderSwatchSVG(style){
    const c = eyeCorners(style, 20);
    return `<svg viewBox="0 0 24 24">
      <path d="${roundedRectPath(2,2,20,20,c.tl,c.tr,c.br,c.bl)}" fill="none" stroke="#14181A" stroke-width="3.4"/>
    </svg>`;
  }
  function eyeCenterSwatchSVG(style){
    if(DECOR_SHAPES.includes(style)) return `<svg viewBox="0 0 24 24">${decorShapeMarkup(style,3,3,18,'#14181A')}</svg>`;
    const c = eyeCorners(style, 18);
    return `<svg viewBox="0 0 24 24"><path d="${roundedRectPath(3,3,18,18,c.tl,c.tr,c.br,c.bl)}" fill="#14181A"/></svg>`;
  }
  function frameSwatchSVG(style){
    if(style==='none') return `<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" fill="none" stroke="#CBD1CC" stroke-width="1.5" stroke-dasharray="2 2"/><rect x="8" y="8" width="8" height="8" fill="#14181A"/></svg>`;
    if(style==='bottom-rect') return `<svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="1" fill="none" stroke="#146356" stroke-width="1.6"/><rect x="2" y="16" width="20" height="6" fill="#146356"/></svg>`;
    if(style==='bottom-rounded') return `<svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" fill="none" stroke="#146356" stroke-width="1.6"/><path d="M2 15h20v4a3 3 0 01-3 3H5a3 3 0 01-3-3z" fill="#146356"/></svg>`;
    if(style==='top-text') return `<svg viewBox="0 0 24 24"><rect x="3" y="9" width="18" height="2" fill="#146356"/><rect x="7" y="14" width="10" height="7" fill="#14181A"/></svg>`;
    if(style==='pill') return `<svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="4" fill="none" stroke="#146356" stroke-width="1.6"/><rect x="6" y="17" width="12" height="6" rx="3" fill="#146356"/></svg>`;
    if(style==='corners') return `<svg viewBox="0 0 24 24"><path d="M3 8V3h5M21 8V3h-5M3 16v5h5M21 16v5h-5" fill="none" stroke="#146356" stroke-width="1.8" stroke-linecap="round"/></svg>`;
    return '';
  }

  /* ---------------- Render de campos por tipo ---------------- */
  function fieldsHTML(){
    const f = state.fields, t = state.type;
    if(t==='url') return `
      <div class="field"><label>Tu sitio web o enlace</label>
        <input type="url" id="f-url" placeholder="https://tusitio.com" value="${f.url.value}"></div>`;
    if(t==='text') return `
      <div class="field"><label>Contenido de texto</label>
        <textarea id="f-text" placeholder="Escribe cualquier texto…">${f.text.value}</textarea></div>`;
    if(t==='email') return `
      <div class="field"><label>Correo destino</label><input type="email" id="f-email-to" placeholder="hola@empresa.com" value="${f.email.to}"></div>
      <div class="field"><label>Asunto</label><input type="text" id="f-email-subject" placeholder="Asunto (opcional)" value="${f.email.subject}"></div>
      <div class="field"><label>Mensaje</label><textarea id="f-email-body" placeholder="Cuerpo del mensaje (opcional)">${f.email.body}</textarea></div>`;
    if(t==='tel') return `
      <div class="field"><label>Número de teléfono</label><input type="tel" id="f-tel" placeholder="+52 55 1234 5678" value="${f.tel.value}"></div>`;
    if(t==='sms') return `
      <div class="field"><label>Número de teléfono</label><input type="tel" id="f-sms-phone" placeholder="+52 55 1234 5678" value="${f.sms.phone}"></div>
      <div class="field"><label>Mensaje predefinido</label><textarea id="f-sms-msg" placeholder="Mensaje (opcional)">${f.sms.message}</textarea></div>`;
    if(t==='whatsapp') return `
      <div class="field"><label>Número, enlace o código de contacto de WhatsApp</label>
        <input type="text" id="f-wa-phone" placeholder="+52 55 1234 5678 · o pega tu enlace wa.me" value="${f.whatsapp.phone}"></div>
      <p class="hint">Lo más confiable es pegar el <b>enlace completo</b> que te da WhatsApp (por ejemplo https://wa.me/qr/TUCÓDIGO): se usa tal cual. También acepta un número con código de país. Si solo pegas el código suelto, se arma como wa.me/qr/CÓDIGO.</p>
      <div class="field"><label>Mensaje predefinido</label><textarea id="f-wa-msg" placeholder="Mensaje (opcional)">${f.whatsapp.message}</textarea></div>`;
    if(t==='wifi') return `
      <div class="field"><label>Nombre de la red (SSID)</label><input type="text" id="f-wifi-ssid" placeholder="MiRedWiFi" value="${f.wifi.ssid}"></div>
      <div class="field-row">
        <div class="field"><label>Contraseña</label><input type="text" id="f-wifi-pass" placeholder="••••••••" value="${f.wifi.password}"></div>
        <div class="field"><label>Seguridad</label>
          <select id="f-wifi-enc">
            <option value="WPA" ${f.wifi.enc==='WPA'?'selected':''}>WPA/WPA2</option>
            <option value="WEP" ${f.wifi.enc==='WEP'?'selected':''}>WEP</option>
            <option value="nopass" ${f.wifi.enc==='nopass'?'selected':''}>Sin contraseña</option>
          </select>
        </div>
      </div>
      <label class="field-check"><input type="checkbox" id="f-wifi-hidden" ${f.wifi.hidden?'checked':''}> Red oculta</label>`;
    if(t==='vcard') return `
      <div class="field-row">
        <div class="field"><label>Nombre completo</label><input type="text" id="f-vc-name" placeholder="Nombre Apellido" value="${f.vcard.name}"></div>
        <div class="field"><label>Puesto</label><input type="text" id="f-vc-title" placeholder="Director de TI" value="${f.vcard.title}"></div>
      </div>
      <div class="field"><label>Empresa</label><input type="text" id="f-vc-org" placeholder="Empresa" value="${f.vcard.org}"></div>
      <div class="field-row">
        <div class="field"><label>Teléfono</label><input type="tel" id="f-vc-phone" placeholder="+52 55 1234 5678" value="${f.vcard.phone}"></div>
        <div class="field"><label>Correo</label><input type="email" id="f-vc-email" placeholder="correo@empresa.com" value="${f.vcard.email}"></div>
      </div>
      <div class="field"><label>Sitio web</label><input type="url" id="f-vc-web" placeholder="https://empresa.com" value="${f.vcard.website}"></div>`;
    if(t==='event') return `
      <div class="field"><label>Título del evento</label><input type="text" id="f-ev-title" placeholder="Reunión anual" value="${f.event.title}"></div>
      <div class="field-row">
        <div class="field"><label>Inicio</label><input type="datetime-local" id="f-ev-start" value="${f.event.start}"></div>
        <div class="field"><label>Fin</label><input type="datetime-local" id="f-ev-end" value="${f.event.end}"></div>
      </div>
      <div class="field"><label>Ubicación</label><input type="text" id="f-ev-loc" placeholder="Lugar (opcional)" value="${f.event.location}"></div>
      <div class="field"><label>Descripción</label><textarea id="f-ev-desc" placeholder="Detalles (opcional)">${f.event.desc}</textarea></div>`;
    if(t==='image') return `
      <div class="field"><label>Enlace de la imagen</label>
        <input type="url" id="f-image-url" placeholder="https://misitio.com/foto.jpg" value="${f.image.url}"></div>
      <p class="hint">Esta app no aloja archivos: pega el enlace directo de una imagen ya subida (Drive, Dropbox, tu propio servidor, etc.).</p>`;
    if(t==='video') return `
      <div class="field"><label>Enlace del video</label>
        <input type="url" id="f-video-url" placeholder="https://youtube.com/watch?v=..." value="${f.video.url}"></div>
      <p class="hint">Funciona con YouTube, Vimeo o cualquier enlace directo a un video ya alojado.</p>`;
    if(t==='pdf') return `
      <div class="field"><label>Enlace del PDF</label>
        <input type="url" id="f-pdf-url" placeholder="https://misitio.com/documento.pdf" value="${f.pdf.url}"></div>
      <p class="hint">Pega el enlace directo del PDF ya alojado (Drive, Dropbox, tu sitio web, etc.).</p>`;
    if(t==='social') return `
      <div class="field"><label>Plataforma</label>
        <select id="f-social-platform">
          <option value="instagram" ${f.social.platform==='instagram'?'selected':''}>Instagram</option>
          <option value="facebook" ${f.social.platform==='facebook'?'selected':''}>Facebook</option>
          <option value="tiktok" ${f.social.platform==='tiktok'?'selected':''}>TikTok</option>
          <option value="x" ${f.social.platform==='x'?'selected':''}>X (Twitter)</option>
          <option value="linkedin" ${f.social.platform==='linkedin'?'selected':''}>LinkedIn</option>
          <option value="youtube" ${f.social.platform==='youtube'?'selected':''}>YouTube</option>
          <option value="threads" ${f.social.platform==='threads'?'selected':''}>Threads</option>
        </select></div>
      <div class="field"><label>Usuario o handle</label>
        <input type="text" id="f-social-username" placeholder="@tuempresa" value="${f.social.username}"></div>`;
    if(t==='spotify') return `
      <div class="field"><label>Enlace o URI de Spotify</label>
        <input type="url" id="f-spotify-url" placeholder="https://open.spotify.com/track/..." value="${f.spotify.url}"></div>
      <p class="hint">Pega el enlace para compartir desde Spotify (canción, álbum, artista o playlist). También acepta el formato spotify:track:...</p>`;
    return '';
  }

  function bindFieldEvents(){
    const on = (id, cb) => { const el = document.getElementById(id); if(el) el.addEventListener('input', cb); };
    const f = state.fields, t = state.type;
    if(t==='url') on('f-url', e=>{ f.url.value = e.target.value; renderPreview(); });
    if(t==='text') on('f-text', e=>{ f.text.value = e.target.value; renderPreview(); });
    if(t==='email'){
      on('f-email-to', e=>{ f.email.to=e.target.value; renderPreview(); });
      on('f-email-subject', e=>{ f.email.subject=e.target.value; renderPreview(); });
      on('f-email-body', e=>{ f.email.body=e.target.value; renderPreview(); });
    }
    if(t==='tel') on('f-tel', e=>{ f.tel.value=e.target.value; renderPreview(); });
    if(t==='sms'){
      on('f-sms-phone', e=>{ f.sms.phone=e.target.value; renderPreview(); });
      on('f-sms-msg', e=>{ f.sms.message=e.target.value; renderPreview(); });
    }
    if(t==='whatsapp'){
      on('f-wa-phone', e=>{ f.whatsapp.phone=e.target.value; renderPreview(); });
      on('f-wa-msg', e=>{ f.whatsapp.message=e.target.value; renderPreview(); });
    }
    if(t==='wifi'){
      on('f-wifi-ssid', e=>{ f.wifi.ssid=e.target.value; renderPreview(); });
      on('f-wifi-pass', e=>{ f.wifi.password=e.target.value; renderPreview(); });
      const enc = document.getElementById('f-wifi-enc'); if(enc) enc.addEventListener('change', e=>{ f.wifi.enc=e.target.value; renderPreview(); });
      const hid = document.getElementById('f-wifi-hidden'); if(hid) hid.addEventListener('change', e=>{ f.wifi.hidden=e.target.checked; renderPreview(); });
    }
    if(t==='vcard'){
      on('f-vc-name', e=>{ f.vcard.name=e.target.value; renderPreview(); });
      on('f-vc-title', e=>{ f.vcard.title=e.target.value; renderPreview(); });
      on('f-vc-org', e=>{ f.vcard.org=e.target.value; renderPreview(); });
      on('f-vc-phone', e=>{ f.vcard.phone=e.target.value; renderPreview(); });
      on('f-vc-email', e=>{ f.vcard.email=e.target.value; renderPreview(); });
      on('f-vc-web', e=>{ f.vcard.website=e.target.value; renderPreview(); });
    }
    if(t==='event'){
      on('f-ev-title', e=>{ f.event.title=e.target.value; renderPreview(); });
      on('f-ev-start', e=>{ f.event.start=e.target.value; renderPreview(); });
      on('f-ev-end', e=>{ f.event.end=e.target.value; renderPreview(); });
      on('f-ev-loc', e=>{ f.event.location=e.target.value; renderPreview(); });
      on('f-ev-desc', e=>{ f.event.desc=e.target.value; renderPreview(); });
    }
    if(t==='image') on('f-image-url', e=>{ f.image.url=e.target.value; renderPreview(); });
    if(t==='video') on('f-video-url', e=>{ f.video.url=e.target.value; renderPreview(); });
    if(t==='pdf') on('f-pdf-url', e=>{ f.pdf.url=e.target.value; renderPreview(); });
    if(t==='social'){
      const plat = document.getElementById('f-social-platform'); if(plat) plat.addEventListener('change', e=>{ f.social.platform=e.target.value; renderPreview(); });
      on('f-social-username', e=>{ f.social.username=e.target.value; renderPreview(); });
    }
    if(t==='spotify') on('f-spotify-url', e=>{ f.spotify.url=e.target.value; renderPreview(); });
  }

  /* ---------------- Render de paneles de diseño ---------------- */
  function frameHTML(){
    const styles = ['none','bottom-rect','bottom-rounded','top-text','pill','corners'];
    return `
      <div class="pane-toolbar"><button type="button" class="btn-reset" id="reset-frame">${icon('reset',13)} Restablecer marco</button></div>
      <div class="swatch-grid">
        ${styles.map(s=>`<button type="button" class="swatch ${state.frame.style===s?'active':''}" data-frame="${s}" title="${s}">${frameSwatchSVG(s)}</button>`).join('')}
      </div>
      ${state.frame.style!=='none' ? `
      <div class="field"><label>Texto del marco</label><input type="text" id="frame-text" value="${state.frame.text}" maxlength="24"></div>
      <div class="field-row">
        <div class="field"><label>Color del marco</label>
          <div class="color-row"><input type="color" id="frame-color" value="${state.frame.color}"><input type="text" id="frame-color-hex" value="${state.frame.color}"></div>
        </div>
        <div class="field"><label>Color del texto</label>
          <div class="color-row"><input type="color" id="frame-textcolor" value="${state.frame.textColor}"><input type="text" id="frame-textcolor-hex" value="${state.frame.textColor}"></div>
        </div>
      </div>` : `<p class="hint">Sin marco: el código QR se descarga limpio, sin borde ni texto.</p>`}
    `;
  }

  function shapeHTML(){
    const s = state.shape;
    const dotStyles = ['square','rounded','dots','classy','star','diamond','cross','plus','heart','clover'];
    const borderStyles = ['square','rounded','circle','leaf-tl','leaf-tr','leaf-br','leaf-bl','diagonal1','diagonal2'];
    const centerStyles = ['square','rounded','circle','leaf-tl','leaf-tr','leaf-br','leaf-bl','diagonal1','diagonal2','star','diamond','cross','plus','clover','heart'];
    return `
      <div class="pane-toolbar"><button type="button" class="btn-reset" id="reset-shape">${icon('reset',13)} Restablecer forma</button></div>
      <div class="divider-label">Forma de los módulos</div>
      <div class="swatch-grid">
        ${dotStyles.map(v=>`<button type="button" class="swatch ${s.dotStyle===v?'active':''}" data-dot="${v}" title="${v}">${dotSwatchSVG(v)}</button>`).join('')}
      </div>
      <div class="field-row">
        <div class="field"><label>Color de fondo</label>
          <div class="color-row"><input type="color" id="bg-color" value="${s.bgColor}" ${s.transparentBg?'disabled':''}><input type="text" id="bg-color-hex" value="${s.bgColor}" ${s.transparentBg?'disabled':''}></div>
        </div>
        <div class="field"><label>Color de la forma</label>
          <div class="color-row"><input type="color" id="fg-color" value="${s.fgColor}"><input type="text" id="fg-color-hex" value="${s.fgColor}"></div>
        </div>
      </div>
      <button type="button" class="btn" id="invert-fgbg" style="width:auto;flex-direction:row;gap:6px;margin-bottom:14px;padding:8px 14px">
        <svg viewBox="0 0 24 24" width="15" height="15"><path d="M7 7h10M7 7l3-3M7 7l3 3M17 17H7m10 0l-3 3m3-3l-3-3" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Invertir colores
      </button>
      <div class="toggle-row"><span>Fondo transparente</span>
        <label class="switch"><input type="checkbox" id="bg-transparent" ${s.transparentBg?'checked':''}><span class="slider-toggle"></span></label></div>
      <div class="toggle-row"><span>Degradado en la forma</span>
        <label class="switch"><input type="checkbox" id="gradient-toggle" ${s.gradient?'checked':''}><span class="slider-toggle"></span></label></div>
      ${s.gradient ? `
      <div class="field-row">
        <div class="field"><label>Segundo color</label>
          <div class="color-row"><input type="color" id="fg-color2" value="${s.fgColor2}"><input type="text" id="fg-color2-hex" value="${s.fgColor2}"></div>
        </div>
        <div class="range-row"><div class="range-row-head"><span>Ángulo</span><span>${s.gradientAngle}°</span></div>
          <input type="range" id="gradient-angle" min="0" max="360" value="${s.gradientAngle}"></div>
      </div>` : ''}

      <div class="divider-label">Espacio alrededor (zona de silencio)</div>
      <div class="range-row">
        <div class="range-row-head"><span>Margen en módulos</span><span>${s.quietZone}</span></div>
        <input type="range" id="quiet-zone" min="0" max="10" value="${s.quietZone}">
      </div>
      <p class="hint">El estándar recomienda al menos 4 módulos de margen para que las cámaras enfoquen bien el código. Reducirlo puede afectar la lectura en algunos dispositivos.</p>

      <div class="divider-label">Estilo de borde del ojo (esquina exterior)</div>
      <div class="swatch-grid">
        ${borderStyles.map(v=>`<button type="button" class="swatch ${s.eyeBorderStyle===v?'active':''}" data-eyeborder="${v}" title="${v}">${eyeBorderSwatchSVG(v)}</button>`).join('')}
      </div>
      <div class="field"><label>Color del borde del ojo</label>
        <div class="color-row"><input type="color" id="eyeborder-color" value="${s.eyeBorderColor}"><input type="text" id="eyeborder-color-hex" value="${s.eyeBorderColor}"></div>
      </div>

      <div class="divider-label">Estilo de centro del ojo (punto interior)</div>
      <div class="swatch-grid">
        ${centerStyles.map(v=>`<button type="button" class="swatch ${s.eyeCenterStyle===v?'active':''}" data-eyecenter="${v}" title="${v}">${eyeCenterSwatchSVG(v)}</button>`).join('')}
      </div>
      <div class="field"><label>Color del centro del ojo</label>
        <div class="color-row"><input type="color" id="eyecenter-color" value="${s.eyeCenterColor}"><input type="text" id="eyecenter-color-hex" value="${s.eyeCenterColor}"></div>
      </div>
      <button type="button" class="btn" id="invert-eye" style="width:auto;flex-direction:row;gap:6px;margin-top:2px;padding:8px 14px">
        <svg viewBox="0 0 24 24" width="15" height="15"><path d="M7 7h10M7 7l3-3M7 7l3 3M17 17H7m10 0l-3 3m3-3l-3-3" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Invertir colores de ojos
      </button>
    `;
  }

  const MAX_LOGO_BYTES = 2 * 1024 * 1024; // 2 MB

  /* -------- Galería de logos preestablecidos (iconos genéricos) -------- */
  const PRESET_LOGOS = [
    { id:'link', label:'Enlace', bg:'#7C3AED', icon:'<path d="M9 15l6-6M8 12l-2.5 2.5a3.5 3.5 0 105 5L13 17M11 7l2.5-2.5a3.5 3.5 0 115 5L16 12"/>' },
    { id:'location', label:'Ubicación', bg:'#E23744', icon:'<path d="M12 21s7-6.5 7-11a7 7 0 10-14 0c0 4.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.3" fill="#fff" stroke="none"/>' },
    { id:'email', label:'Email', bg:'#F5A623', icon:'<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M4 7l8 6 8-6"/>' },
    { id:'call', label:'Llamada', bg:'#2FBF71', icon:'<path d="M6 3.5c1.2 0 2.3.8 2.7 2l.6 1.8c.3.9 0 1.9-.7 2.5l-1 .9a12 12 0 006.2 6.2l.9-1c.6-.7 1.6-1 2.5-.7l1.8.6c1.2.4 2 1.5 2 2.7v1.3c0 1.5-1.3 2.6-2.8 2.4C10.7 21 3 13.3 2.3 5.8 2.1 4.3 3.2 3 4.7 3z"/>' },
    { id:'wifi', label:'Wi-Fi', bg:'#17A6A6', icon:'<path d="M3.5 9.5a13 13 0 0117 0M6.5 12.7a9 9 0 0111 0M9.7 15.8a5 5 0 014.6 0"/><circle cx="12" cy="19" r="1.1" fill="#fff" stroke="none"/>' },
    { id:'card', label:'Tarjeta', bg:'#2563EB', icon:'<rect x="3" y="5" width="18" height="14" rx="2.2"/><circle cx="8.3" cy="10.6" r="1.7"/><path d="M5.3 15.5c.5-1.6 1.7-2.3 3-2.3s2.5.7 3 2.3M14 10h5M14 13.3h5"/>' },
    { id:'percent', label:'Descuento', bg:'#EC4899', icon:'<circle cx="7" cy="7" r="2.2" fill="#fff" stroke="none"/><circle cx="17" cy="17" r="2.2" fill="#fff" stroke="none"/><path d="M6 18L18 6"/>' },
    { id:'pay', label:'Pago', bg:'#F59E0B', icon:'<circle cx="12" cy="12" r="8.3"/><path d="M12 7v10M9.3 9.4c0-1 1.1-1.8 2.7-1.8s2.7.7 2.7 1.7c0 2.5-5.4 1.3-5.4 3.8 0 1 1.1 1.7 2.7 1.7s2.7-.7 2.7-1.7"/>' },
    { id:'scan1', label:'Escanear', bg:'#14181A', icon:'<path d="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3"/><rect x="9" y="9" width="6" height="6" rx="1" fill="#fff" stroke="none"/>' },
    { id:'scan2', label:'Escanear (círculo)', bg:'#14181A', icon:'<path d="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3"/><circle cx="12" cy="12" r="3" fill="#fff" stroke="none"/>' },
    { id:'barcode', label:'Código de barras', bg:'#334155', icon:'<path d="M4 5v14M7 5v14M9.5 5v14M13 5v14M15.5 5v14M18 5v14M20.5 5v14" stroke-width="1.5"/>' },
    { id:'pdf', label:'PDF', bg:'#DC2626', icon:'<path d="M7 3h7l4 4v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v4h4"/><text x="7.2" y="16.2" font-size="5.6" font-weight="700" fill="#fff" stroke="none" font-family="Arial,sans-serif">PDF</text>' },
    { id:'star', label:'Destacado', bg:'#D4A017', icon:'<polygon points="12,3 14.6,9.6 21.5,10 16.2,14.3 18,21 12,17.2 6,21 7.8,14.3 2.5,10 9.4,9.6" fill="#fff" stroke="none"/>' },
    { id:'camera', label:'Foto', bg:'#C2417A', icon:'<rect x="3" y="7" width="18" height="13" rx="2.5"/><circle cx="12" cy="13.5" r="3.4"/><path d="M8 7l1.4-2h5.2L16 7"/>' },
    { id:'chat', label:'Mensaje', bg:'#1E293B', icon:'<path d="M4 5.5h16a1 1 0 011 1V15a1 1 0 01-1 1H9l-4 4v-4H4a1 1 0 01-1-1V6.5a1 1 0 011-1z"/>' },
    { id:'play', label:'Video', bg:'#E11D2E', icon:'<circle cx="12" cy="12" r="8.6"/><path d="M10 8.6l6 3.4-6 3.4z" fill="#fff" stroke="none"/>' },
    { id:'note', label:'Música', bg:'#1DB954', icon:'<circle cx="8" cy="17" r="2.2" fill="#fff" stroke="none"/><circle cx="17" cy="15" r="2.2" fill="#fff" stroke="none"/><path d="M10.2 17V6.7L19.2 5v10"/>' },
    { id:'globe', label:'Sitio web', bg:'#4F46E5', icon:'<circle cx="12" cy="12" r="8.6"/><path d="M3.4 12h17.2M12 3.4c2.4 2.4 3.6 5.6 3.6 8.6s-1.2 6.2-3.6 8.6c-2.4-2.4-3.6-5.6-3.6-8.6s1.2-6.2 3.6-8.6z"/>' },
  ];
  function presetLogoDataUrl(preset){
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="32" fill="${preset.bg}"/><g transform="translate(16,16) scale(1.333)" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${preset.icon}</g></svg>`;
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }

  function logoHTML(){
    const l = state.logo;
    return `
      <div class="pane-toolbar"><button type="button" class="btn-reset" id="reset-logo">${icon('reset',13)} Restablecer logo</button></div>
      ${l.dataUrl ? `
        <div class="logo-drop has-logo">
          <div class="logo-preview-row">
            <img src="${l.dataUrl}" alt="logo">
            <span>Logo cargado</span>
            <button type="button" class="logo-remove" id="logo-remove">Quitar</button>
          </div>
        </div>` : `
        <div class="logo-drop" id="logo-drop">
          ${icon('upload',20)}<div style="margin-top:6px">Arrastra y suelta o haz clic para subir un logo</div>
          <div class="hint" style="margin-top:2px">Formatos JPG, PNG o SVG · máx. 2&nbsp;MB</div>
        </div>`}
      <input type="file" id="logo-input" accept="image/png,image/jpeg,image/svg+xml" style="display:none">
      <div class="err-msg" id="logo-err-msg"></div>

      <div class="divider-label">O elige de la galería</div>
      <div class="swatch-grid">
        ${PRESET_LOGOS.map(p=>`<button type="button" class="swatch" data-preset-logo="${p.id}" title="${p.label}"><img src="${presetLogoDataUrl(p)}" alt="${p.label}" style="width:100%;height:100%;border-radius:6px"></button>`).join('')}
      </div>

      ${l.dataUrl ? `
      <div class="range-row" style="margin-top:14px">
        <div class="range-row-head"><span>Tamaño del logo</span><span>${l.sizePct}%</span></div>
        <input type="range" id="logo-size" min="10" max="35" value="${l.sizePct}">
      </div>
      <div class="toggle-row"><span>Eliminar fondo detrás del logo</span>
        <label class="switch"><input type="checkbox" id="logo-removebg" ${l.removeBg?'checked':''}><span class="slider-toggle"></span></label></div>
      ${l.removeBg ? `
      <div class="toggle-row"><span>Espacio de seguridad alrededor</span>
        <label class="switch"><input type="checkbox" id="logo-margin" ${l.margin?'checked':''}><span class="slider-toggle"></span></label></div>` : ''}
      <p class="hint">Con logo se usa automáticamente corrección de errores alta (H) para mantener el código legible.</p>` : ''}
    `;
  }

  const EC_LEVELS = [
    { id:'L', pct:'7%', desc:'Capacidad máxima, menor tolerancia a daños.' },
    { id:'M', pct:'15%', desc:'Equilibrio recomendado para uso general.' },
    { id:'Q', pct:'25%', desc:'Más resistente a manchas o dobleces.' },
    { id:'H', pct:'30%', desc:'Máxima tolerancia; ideal con logo encima.' },
  ];

  // Miniatura real del QR (sin marco ni logo) para comparar la densidad de cada nivel
  function buildLevelPreviewSVG(level){
    try{
      const payload = buildPayload();
      const matrix = getMatrixForLevel(payload, level);
      const quiet = 2;
      const qrPix = (matrix.count + quiet*2) * MODULE;
      const ox = quiet*MODULE, oy = quiet*MODULE;
      const modules = buildModulesMarkup(matrix, ox, oy, state.shape.fgColor);
      const eyes = buildEyesMarkup(matrix, ox, oy, state.shape.eyeBorderColor, state.shape.eyeCenterColor);
      const bg = state.shape.transparentBg ? 'none' : state.shape.bgColor;
      const bgRect = bg !== 'none' ? `<rect x="0" y="0" width="${qrPix}" height="${qrPix}" fill="${bg}"/>` : '';
      return `<svg viewBox="0 0 ${qrPix} ${qrPix}" xmlns="http://www.w3.org/2000/svg">${bgRect}<path d="${modules.d}" fill="${state.shape.fgColor}"/>${modules.extra}${eyes}</svg>`;
    }catch(e){
      return `<svg viewBox="0 0 100 100"><text x="6" y="54" font-size="11" fill="#B23B2E">Contenido muy largo</text></svg>`;
    }
  }

  function levelHTML(){
    const forced = !!state.logo.dataUrl;
    return `
      <div class="pane-toolbar"><button type="button" class="btn-reset" id="reset-level">${icon('reset',13)} Restablecer nivel</button></div>
      <p class="hint" style="margin-bottom:14px">Nivel de corrección de errores: cuanto más alto, más se puede dañar o cubrir el código (por ejemplo con un logo) sin perder legibilidad — a cambio, el patrón se ve más denso.</p>
      <div class="level-grid">
        ${EC_LEVELS.map(l => `
          <button type="button" class="level-card ${state.ecLevel===l.id?'active':''}" data-level="${l.id}" ${forced?'disabled':''}>
            <div class="level-preview">${buildLevelPreviewSVG(l.id)}</div>
            <span class="level-id">Nivel ${l.id}</span>
            <span class="level-pct">${l.pct}</span>
            <span class="level-desc">${l.desc}</span>
          </button>`).join('')}
      </div>
      ${forced ? `<p class="hint" style="margin-top:12px">Tienes un logo activo, así que se usa automáticamente el <b>Nivel H</b> para conservar la legibilidad. Quita el logo para elegir otro nivel.</p>` : ''}
    `;
  }

  function bindColorPair(colorId, hexId, onChange){
    const c = document.getElementById(colorId), h = document.getElementById(hexId);
    if(!c||!h) return;
    c.addEventListener('input', e=>{ h.value = e.target.value; onChange(e.target.value); });
    h.addEventListener('input', e=>{
      let v = e.target.value;
      if(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v)){ c.value = v; onChange(v); }
    });
  }

  function bindDesignEvents(){
    document.querySelectorAll('[data-frame]').forEach(btn=>{
      btn.addEventListener('click', ()=>{ state.frame.style = btn.dataset.frame; renderDesignPane(); renderPreview(); });
    });
    document.querySelectorAll('[data-dot]').forEach(btn=>{
      btn.addEventListener('click', ()=>{ state.shape.dotStyle = btn.dataset.dot; renderDesignPane(); renderPreview(); });
    });
    document.querySelectorAll('[data-eyeborder]').forEach(btn=>{
      btn.addEventListener('click', ()=>{ state.shape.eyeBorderStyle = btn.dataset.eyeborder; renderDesignPane(); renderPreview(); });
    });
    document.querySelectorAll('[data-eyecenter]').forEach(btn=>{
      btn.addEventListener('click', ()=>{ state.shape.eyeCenterStyle = btn.dataset.eyecenter; renderDesignPane(); renderPreview(); });
    });

    const ft = document.getElementById('frame-text'); if(ft) ft.addEventListener('input', e=>{ state.frame.text=e.target.value; renderPreview(); });
    bindColorPair('frame-color','frame-color-hex', v=>{ state.frame.color=v; renderPreview(); });
    bindColorPair('frame-textcolor','frame-textcolor-hex', v=>{ state.frame.textColor=v; renderPreview(); });
    bindColorPair('bg-color','bg-color-hex', v=>{ state.shape.bgColor=v; renderPreview(); });
    bindColorPair('fg-color','fg-color-hex', v=>{ state.shape.fgColor=v; renderPreview(); });
    bindColorPair('fg-color2','fg-color2-hex', v=>{ state.shape.fgColor2=v; renderPreview(); });
    bindColorPair('eyeborder-color','eyeborder-color-hex', v=>{ state.shape.eyeBorderColor=v; renderPreview(); });
    bindColorPair('eyecenter-color','eyecenter-color-hex', v=>{ state.shape.eyeCenterColor=v; renderPreview(); });

    const invFgBg = document.getElementById('invert-fgbg');
    if(invFgBg) invFgBg.addEventListener('click', ()=>{
      const s = state.shape;
      [s.fgColor, s.bgColor] = [s.bgColor, s.fgColor];
      renderDesignPane(); renderPreview();
    });
    const invEye = document.getElementById('invert-eye');
    if(invEye) invEye.addEventListener('click', ()=>{
      const s = state.shape;
      [s.eyeBorderColor, s.eyeCenterColor] = [s.eyeCenterColor, s.eyeBorderColor];
      renderDesignPane(); renderPreview();
    });

    const bt = document.getElementById('bg-transparent'); if(bt) bt.addEventListener('change', e=>{ state.shape.transparentBg=e.target.checked; renderDesignPane(); renderPreview(); });
    const gt = document.getElementById('gradient-toggle'); if(gt) gt.addEventListener('change', e=>{ state.shape.gradient=e.target.checked; renderDesignPane(); renderPreview(); });
    const ga = document.getElementById('gradient-angle'); if(ga) ga.addEventListener('input', e=>{ state.shape.gradientAngle=+e.target.value; renderDesignPane(); renderPreview(); });
    const qz = document.getElementById('quiet-zone'); if(qz) qz.addEventListener('input', e=>{ state.shape.quietZone=+e.target.value; renderDesignPane(); renderPreview(); });

    const drop = document.getElementById('logo-drop'), input = document.getElementById('logo-input');
    if(drop) drop.addEventListener('click', ()=> input.click());
    if(input) input.addEventListener('change', e=>{
      const file = e.target.files[0]; if(!file) return;
      const errEl = document.getElementById('logo-err-msg');
      if(file.size > MAX_LOGO_BYTES){
        if(errEl){ errEl.textContent = `El archivo pesa ${(file.size/1024/1024).toFixed(1)} MB. El máximo permitido es 2 MB.`; errEl.classList.add('show'); }
        input.value = '';
        return;
      }
      if(errEl) errEl.classList.remove('show');
      const reader = new FileReader();
      reader.onload = ev => { state.logo.dataUrl = ev.target.result; renderDesignPane(); renderPreview(); };
      reader.readAsDataURL(file);
    });
    document.querySelectorAll('[data-preset-logo]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const preset = PRESET_LOGOS.find(p => p.id === btn.dataset.presetLogo);
        if(preset){ state.logo.dataUrl = presetLogoDataUrl(preset); renderDesignPane(); renderPreview(); }
      });
    });
    const rm = document.getElementById('logo-remove'); if(rm) rm.addEventListener('click', ()=>{ state.logo.dataUrl=null; renderDesignPane(); renderPreview(); });
    const ls = document.getElementById('logo-size'); if(ls) ls.addEventListener('input', e=>{ state.logo.sizePct=+e.target.value; renderDesignPane(); renderPreview(); });
    const lm = document.getElementById('logo-margin'); if(lm) lm.addEventListener('change', e=>{ state.logo.margin=e.target.checked; renderPreview(); });
    const lrb = document.getElementById('logo-removebg'); if(lrb) lrb.addEventListener('change', e=>{ state.logo.removeBg=e.target.checked; renderDesignPane(); renderPreview(); });
  }

  let activeDesignTab = 'frame';
  function renderDesignPane(){
    document.getElementById('design-pane-frame').innerHTML = frameHTML();
    document.getElementById('design-pane-shape').innerHTML = shapeHTML();
    document.getElementById('design-pane-logo').innerHTML = logoHTML();
    document.getElementById('design-pane-level').innerHTML = levelHTML();
    bindDesignEvents();
    const rf = document.getElementById('reset-frame'); if(rf) rf.addEventListener('click', resetFrame);
    const rs = document.getElementById('reset-shape'); if(rs) rs.addEventListener('click', resetShape);
    const rl = document.getElementById('reset-logo'); if(rl) rl.addEventListener('click', resetLogo);
    const rlv = document.getElementById('reset-level'); if(rlv) rlv.addEventListener('click', resetLevel);
    document.querySelectorAll('[data-level]').forEach(btn=>{
      btn.addEventListener('click', ()=>{ if(btn.disabled) return; state.ecLevel = btn.dataset.level; renderDesignPane(); renderPreview(); });
    });
  }

  /* ---------------- Render principal ---------------- */
  function renderTypeTabs(){
    const el = document.getElementById('type-tabs');
    el.innerHTML = CONTENT_TYPES.map(ct => `
      <button type="button" class="type-tab ${state.type===ct.id?'active':''}" data-type="${ct.id}">
        ${icon(ct.icon,16)}<span>${ct.label}</span>
      </button>`).join('');
    el.querySelectorAll('[data-type]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        state.type = btn.dataset.type;
        renderTypeTabs();
        document.getElementById('content-fields').innerHTML = fieldsHTML();
        bindFieldEvents();
        renderPreview();
      });
    });
  }

  function renderPreview(){
    let result;
    const errEl = document.getElementById('err-msg');
    try{
      result = buildSVG();
      errEl.classList.remove('show');
    }catch(e){
      errEl.textContent = 'El contenido es demasiado largo para generar un código QR legible. Prueba con menos texto.';
      errEl.classList.add('show');
      return;
    }
    document.getElementById('preview-canvas').innerHTML = result.svg;
    document.getElementById('preview-dims').textContent = `${result.W}×${result.H} px`;
    window.__qrLastResult = result;
    updateSizeLabel();
  }

  /* ---------------- Exportación ---------------- */
  function svgElementString(){
    const svgEl = document.querySelector('#preview-canvas svg');
    const clone = svgEl.cloneNode(true);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    return new XMLSerializer().serializeToString(clone);
  }

  function downloadBlob(blob, filename){
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 4000);
  }

  function exportSVG(){
    const str = svgElementString();
    downloadBlob(new Blob([str], {type:'image/svg+xml'}), 'codigo-qr.svg');
  }

  /* ---------------- Exportar / importar proyecto completo (JSON) ---------------- */
  // Los logos ya se guardan como data: URI (base64 para imágenes subidas, texto SVG
  // para los presets), así que forman parte del JSON sin ningún paso extra de codificación.
  const PROJECT_SCHEMA_VERSION = 1;

  function exportProjectJSON(){
    const project = {
      __qrStudioProject: true,
      version: PROJECT_SCHEMA_VERSION,
      exportedAt: new Date().toISOString(),
      type: state.type,
      fields: state.fields,
      frame: state.frame,
      shape: state.shape,
      logo: state.logo,
      ecLevel: state.ecLevel,
    };
    const json = JSON.stringify(project, null, 2);
    downloadBlob(new Blob([json], {type:'application/json'}), 'qr-proyecto.json');
  }

  function showProjectIOError(msg){
    const el = document.getElementById('project-io-err');
    if(!el) return;
    el.textContent = msg;
    el.classList.add('show');
  }
  function clearProjectIOError(){
    const el = document.getElementById('project-io-err');
    if(el) el.classList.remove('show');
  }

  function importProjectJSON(file){
    clearProjectIOError();
    const reader = new FileReader();
    reader.onload = ev => {
      let parsed;
      try{ parsed = JSON.parse(ev.target.result); }
      catch(e){ showProjectIOError('El archivo no es un JSON válido.'); return; }

      if(typeof parsed !== 'object' || parsed === null){
        showProjectIOError('El archivo no tiene el formato esperado.');
        return;
      }

      // Tipo de contenido
      if(typeof parsed.type === 'string' && state.fields[parsed.type]) state.type = parsed.type;

      // Campos por tipo de contenido: fusión superficial por tipo, conservando lo actual
      // si el archivo importado no trae ese tipo (compatibilidad con versiones futuras).
      if(parsed.fields && typeof parsed.fields === 'object'){
        Object.keys(state.fields).forEach(t => {
          if(parsed.fields[t] && typeof parsed.fields[t] === 'object'){
            state.fields[t] = { ...state.fields[t], ...parsed.fields[t] };
          }
        });
      }

      if(parsed.frame && typeof parsed.frame === 'object') state.frame = { ...DEFAULTS.frame, ...parsed.frame };
      if(parsed.shape && typeof parsed.shape === 'object') state.shape = { ...DEFAULTS.shape, ...parsed.shape };
      if(parsed.logo && typeof parsed.logo === 'object') state.logo = { ...DEFAULTS.logo, ...parsed.logo };
      if(typeof parsed.ecLevel === 'string') state.ecLevel = parsed.ecLevel;

      renderTypeTabs();
      document.getElementById('content-fields').innerHTML = fieldsHTML();
      bindFieldEvents();
      renderDesignPane();
      renderPreview();
      clearProjectIOError();
    };
    reader.onerror = () => showProjectIOError('No se pudo leer el archivo.');
    reader.readAsText(file);
  }

  function svgToCanvas(scale){
    return new Promise((resolve,reject)=>{
      const { W, H } = window.__qrLastResult;
      const str = svgElementString();
      const img = new Image();
      const svgBlob = new Blob([str], {type:'image/svg+xml;charset=utf-8'});
      const url = URL.createObjectURL(svgBlob);
      img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(W*scale); canvas.height = Math.round(H*scale);
        const ctx = canvas.getContext('2d');
        if(!state.shape.transparentBg){ ctx.fillStyle = state.shape.bgColor; ctx.fillRect(0,0,canvas.width,canvas.height); }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        resolve(canvas);
      };
      img.onerror = reject;
      img.src = url;
    });
  }

  function updateSizeLabelOnly(){
    const slider = document.getElementById('png-size-slider');
    const label = document.getElementById('png-size-label');
    if(!slider || !label || !window.__qrLastResult) return;
    const { W, H } = window.__qrLastResult;
    const targetW = +slider.value;
    const targetH = Math.round(H * (targetW / W));
    label.textContent = `${targetW} × ${targetH} px`;
  }

  function updateSizeLabel(){
    const slider = document.getElementById('png-size-slider');
    const numInput = document.getElementById('png-size-input');
    const label = document.getElementById('png-size-label');
    if(!slider || !label || !window.__qrLastResult) return;
    const { W, H } = window.__qrLastResult;
    const targetW = +slider.value;
    if(numInput && +numInput.value !== targetW) numInput.value = targetW;
    const targetH = Math.round(H * (targetW / W));
    label.textContent = `${targetW} × ${targetH} px`;
  }

  async function exportPNG(){
    const slider = document.getElementById('png-size-slider');
    const { W } = window.__qrLastResult;
    const targetW = slider ? +slider.value : 1024;
    const scale = targetW / W;
    const canvas = await svgToCanvas(scale);
    canvas.toBlob(blob => downloadBlob(blob, `codigo-qr-${canvas.width}x${canvas.height}px.png`), 'image/png');
  }

  async function exportPDF(){
    const canvas = await svgToCanvas(4);
    const { jsPDF } = window.jspdf;
    const wmm = 100, hmm = wmm * (canvas.height/canvas.width);
    const pdf = new jsPDF({ orientation: wmm>=hmm?'landscape':'portrait', unit:'mm', format:[wmm+20, hmm+20] });
    pdf.setFillColor(255,255,255);
    pdf.rect(0,0,wmm+20,hmm+20,'F');
    pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 10, 10, wmm, hmm);
    pdf.save('codigo-qr.pdf');
  }

  /* ---------------- Inicialización ---------------- */
  function init(){
    renderTypeTabs();
    document.getElementById('content-fields').innerHTML = fieldsHTML();
    bindFieldEvents();
    renderDesignPane();

    document.querySelectorAll('.design-tab').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        document.querySelectorAll('.design-tab').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.design-pane').forEach(p=>p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('design-pane-'+btn.dataset.pane).classList.add('active');
      });
    });

    document.getElementById('btn-png').addEventListener('click', exportPNG);
    const sizeSlider = document.getElementById('png-size-slider');
    const sizeInput = document.getElementById('png-size-input');
    if(sizeSlider) sizeSlider.addEventListener('input', updateSizeLabel);
    if(sizeInput){
      sizeInput.addEventListener('input', ()=>{
        const v = parseInt(sizeInput.value, 10);
        if(!isNaN(v) && sizeSlider){
          sizeSlider.value = Math.min(4096, Math.max(256, v));
          updateSizeLabelOnly();
        }
      });
      sizeInput.addEventListener('change', ()=>{
        let v = parseInt(sizeInput.value, 10);
        if(isNaN(v)) v = +sizeSlider.value;
        v = Math.min(4096, Math.max(256, v));
        sizeInput.value = v;
        if(sizeSlider) sizeSlider.value = v;
        updateSizeLabel();
      });
    }
    document.getElementById('btn-svg').addEventListener('click', exportSVG);
    document.getElementById('btn-pdf').addEventListener('click', exportPDF);
    const ra = document.getElementById('reset-all'); if(ra) ra.addEventListener('click', resetAllDesign);

    const btnExportJson = document.getElementById('btn-export-json');
    if(btnExportJson) btnExportJson.addEventListener('click', exportProjectJSON);
    const btnImportJson = document.getElementById('btn-import-json');
    const importInput = document.getElementById('import-json-input');
    if(btnImportJson && importInput) btnImportJson.addEventListener('click', ()=> importInput.click());
    if(importInput) importInput.addEventListener('change', e=>{
      const file = e.target.files[0];
      if(file) importProjectJSON(file);
      importInput.value = '';
    });

    renderPreview();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
