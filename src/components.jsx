import React,{useEffect,useRef} from 'react';
import {X,Upload,Image,Check,MessageSquare,Users,ArrowUpRight,ChevronDown,Layers,Infinity as LoopIcon} from 'lucide-react';
import {initials} from './data';
import {screenshotFile,prepareScreenshot} from './screenshot-input';
export function Avatar({name='Unassigned',index=0}){return <span className={'avatar color-'+index%3}>{initials(name)}</span>}
export function Badge({status}){return <span className={'badge '+(status==='Open'?'open':status==='Approved'?'approved':'review')}>{status}</span>}
export function Shell({page,setPage,person,onSwitchPerson,projectMenu,children,onValidation}){return <div className="shell" onInvalidCapture={event=>{event.preventDefault();onValidation?.(event.target.validationMessage||'Please check the required fields.');}}><aside><a className="brand" href="#" onClick={e=>{e.preventDefault();setPage('Feedback')}}><LoopIcon size={39} strokeWidth={3}/><span>Loop</span></a><nav><button className={page==='Feedback'?'active':''} onClick={()=>setPage('Feedback')}><MessageSquare size={21}/>Feedback</button><button className={page==='Developers'?'active':''} onClick={()=>setPage('Developers')}><Users size={21}/>Developers</button></nav><button className="current-person" onClick={onSwitchPerson} aria-label="Change person"><Avatar name={person.name}/><span><strong>{person.name}</strong><small>Change person</small></span></button>{projectMenu}</aside><main>{children}</main></div>}
export function Modal({title,onClose,children,wide=false}){const ref=useRef();useEffect(()=>{const before=document.activeElement;ref.current?.focus();const handler=e=>{if(e.key==='Escape')onClose();if(e.key==='Tab'){const all=ref.current.querySelectorAll('button,input,select,textarea,a[href]');const first=all[0],last=all[all.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}};document.addEventListener('keydown',handler);const old=document.body.style.overflow;document.body.style.overflow='hidden';return()=>{document.removeEventListener('keydown',handler);document.body.style.overflow=old;before?.focus()}},[]);return <div className="overlay" onMouseDown={e=>{if(e.target===e.currentTarget)onClose()}}><section ref={ref} tabIndex={-1} className={'modal '+(wide?'wide':'')} role="dialog" aria-modal="true" aria-label={title}><header><h2>{title}</h2><button className="icon-button" aria-label="Close dialog" onClick={onClose}><X size={22}/></button></header>{children}</section></div>}
export function Screenshot({item,onClick}){return <button className="screenshot" onClick={onClick} aria-label={'View screenshot: '+item.title}>{item.image?<img src={item.image} alt={item.title}/>:item.preview!==undefined?<SamplePreview type={item.preview}/>:<span className="no-image"><Image size={25}/>No screenshot</span>}</button>}
function SamplePreview({type}){return <div className={'sample-preview preview-'+type}><div className="sample-nav">Acme <span>Product　 Pricing　 Resources　 ☰</span></div>{type===0?<><h3>Build better<br/>software.</h3><i/><i className="short"/></>:type===1?<div className="mini-center"><h3>Get started today</h3><i/><i/><b>Get started</b></div>:type===2?<><h3 className="center">Pricing</h3><div className="mini-prices">{['Starter','Pro','Team'].map((t,i)=><div key={t}>{t}<strong>${[9,19,49][i]}<small>/mo</small></strong><i/><i/></div>)}</div></>:<><h3>Contact us</h3><div className="mini-input">john@</div><small className="red">Please enter a valid email address.</small></>}</div>}
export function UploadField({value,onChange,onNotify}) {
  const [err,setErr]=React.useState('');
  const [dragging,setDragging]=React.useState(false);
  const [preview,setPreview]=React.useState('');
  const fileInput=useRef();
  const depth=useRef(0);
  const receive=React.useCallback(file=>{
    try { onChange(prepareScreenshot(file)); setErr(''); }
    catch(error) { setErr(error.message); onNotify?.(error.message,'error'); }
  },[onChange,onNotify]);
  useEffect(()=>{
    if(!value) {setPreview('');return;}
    if(typeof value==='string') {setPreview(value);return;}
    const url=URL.createObjectURL(value);setPreview(url);
    return ()=>URL.revokeObjectURL(url);
  },[value]);
  useEffect(()=>{
    const paste=event=>{
      const transfer=event.clipboardData;
      // Ordinary text paste must continue to work in titles and comments.
      const image=Array.from(transfer?.items||[]).find(item=>item.kind==='file'&&item.type.startsWith('image/'));
      if(image) {event.preventDefault();receive(image.getAsFile());}
    };
    const over=event=>{if(Array.from(event.dataTransfer?.types||[]).includes('Files'))event.preventDefault();};
    const drop=event=>{
      if(!Array.from(event.dataTransfer?.types||[]).includes('Files'))return;
      event.preventDefault();depth.current=0;setDragging(false);receive(screenshotFile(event.dataTransfer));
    };
    document.addEventListener('paste',paste);
    document.addEventListener('dragover',over);
    document.addEventListener('drop',drop);
    return ()=>{document.removeEventListener('paste',paste);document.removeEventListener('dragover',over);document.removeEventListener('drop',drop);};
  },[receive]);
  return <div>
    <div className={'upload drop-zone '+(dragging?'dragging':'')}
      onDragEnter={event=>{event.preventDefault();depth.current++;setDragging(true);}}
      onDragOver={event=>{event.preventDefault();event.dataTransfer.dropEffect='copy';}}
      onDragLeave={()=>{depth.current=Math.max(0,depth.current-1);if(!depth.current)setDragging(false);}}
      onDrop={event=>{event.preventDefault();event.stopPropagation();depth.current=0;setDragging(false);receive(screenshotFile(event.dataTransfer));}}>
      <Upload size={25}/>
      <strong>{dragging?'Drop your screenshot here':value?'Screenshot ready':'Drag a screenshot here'}</strong>
      <span>Drag the Mac screenshot thumbnail, paste with ⌘V, or choose a file.</span>
      <button type="button" className="secondary" onClick={()=>fileInput.current?.click()}>{value?'Replace screenshot':'Choose image'}</button>
      <input ref={fileInput} className="visually-hidden" aria-label="Choose screenshot file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" onChange={event=>{const file=event.target.files[0];if(file)receive(file);event.target.value='';}}/>
      <span>PNG, JPG, GIF or WebP · up to 8 MB</span>
    </div>
    <p className="clipboard-tip">Save no file on Mac: press <kbd>⌃⇧⌘4</kbd>, select an area, then press <kbd>⌘V</kbd> here.</p>
    {value&&<div className="selected-screenshot" role="status"><img className="upload-preview" src={preview} alt="Screenshot preview"/><span>{value.name||'Screenshot attached'} · Ready to upload</span></div>}
    {err&&!onNotify&&<p className="error" role="alert">{err}</p>}
  </div>;
}
