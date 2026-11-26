import React, {useEffect, useRef, useState} from 'react';
import {ChevronUp, Plus, Folder, Check, X, CheckCircle2, AlertCircle, Infinity as LoopIcon, UserRound, ArrowRight} from 'lucide-react';
import {Avatar} from './components';
export function usePopover() {
  const [open,setOpen]=useState(false); const ref=useRef(null);
  useEffect(()=>{if(!open)return;const close=e=>{if(e.type==='keydown'){if(e.key==='Escape'){setOpen(false);ref.current?.querySelector('button')?.focus();}}else if(!ref.current?.contains(e.target))setOpen(false);};document.addEventListener('pointerdown',close);document.addEventListener('keydown',close);return()=>{document.removeEventListener('pointerdown',close);document.removeEventListener('keydown',close);};},[open]);
  return {open,setOpen,ref};
}
export function ProjectMenu({projects,project,onSelect,onCreate}) {
  const {open,setOpen,ref}=usePopover(); const current=projects.find(p=>p.id===project);
  return <div className="project-switcher" ref={ref}>
    {open&&<div className="project-popover" role="menu" aria-label="Projects"><div className="popover-heading">Projects / Platforms</div><div className="project-options">{projects.map(p=><button key={p.id} role="menuitemradio" aria-checked={p.id===project} onClick={()=>{onSelect(p.id);setOpen(false);}}><Folder size={17}/><span>{p.name}{p.sample?' (sample)':''}</span>{p.id===project&&<Check size={16}/>}</button>)}</div><button role="menuitem" className="create-project-option" onClick={()=>{setOpen(false);onCreate();}}><Plus size={17}/>Project / Platform</button></div>}
    <button className="project-menu-trigger" aria-label={'Projects: '+(current?.name||'Select project')} aria-haspopup="menu" aria-expanded={open} onClick={()=>setOpen(!open)}><Folder size={19}/><span>{current?.name||'Project / Platform'}</span><ChevronUp size={17} className={open?'expanded':''}/></button>
  </div>;
}
export function PersonPicker({developers,onChoose,loading,error,onRetry}) {
  return <main className="person-screen"><div className="person-panel"><div className="brand person-brand"><LoopIcon size={42} strokeWidth={3}/><span>Loop</span></div><h1>Who are you?</h1><p>Choose your name to join the feedback workspace</p>{loading?<p role="status">Loading your team…</p>:error?<div className="person-error" role="alert"><p>{error}</p><button className="secondary" onClick={onRetry}>Try again</button></div>:<div className="person-options"><button onClick={()=>onChoose({id:'admin',name:'Admin',kind:'admin'})}><span className="admin-avatar"><UserRound size={22}/></span><span><strong>Admin</strong><small>Review feedback and follow up on changes</small></span><ArrowRight size={19}/></button>{developers.map((d,i)=><button key={d.id} onClick={()=>onChoose({id:d.id,name:d.name,kind:'developer'})}><Avatar name={d.name} index={i}/><span><strong>{d.name}</strong><small>Developer</small></span><ArrowRight size={19}/></button>)}</div>}<p className="identity-note">A name for your comments! No password or sign-in required</p></div></main>;
}
export function Toast({toast,onClose}) {
  useEffect(()=>{if(!toast)return;const timer=setTimeout(onClose,5000);return()=>clearTimeout(timer);},[toast,onClose]);
  if(!toast)return null;
  const Icon=toast.type==='error'?AlertCircle:CheckCircle2;
  return <div className={'action-toast '+toast.type} role={toast.type==='error'?'alert':'status'}><Icon size={21}/><div>{toast.message}</div><button className="icon-button" onClick={onClose} aria-label="Dismiss message"><X size={17}/></button></div>;
}
