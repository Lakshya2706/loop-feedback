import React,{useCallback,useEffect,useState} from 'react';
import{createRoot}from'react-dom/client';
import{Shell}from'./components';
import Board from'./Board';
import{FeedbackForm,ProjectForm,Developers}from'./Forms';
import Detail from'./Detail';
import{PersonPicker,ProjectMenu,Toast}from'./Workspace';
import LogoIntro from './LogoIntro';
import{sample,empty}from'./data';
import './styles.css';
function App(){
  const [introDone,setIntroDone]=useState(()=>window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const finishIntro=useCallback(()=>setIntroDone(true),[]);
  const[data,setData]=useState(empty),[revision,setRevision]=useState(''),[project,setProject]=useState('sample'),[page,setPage]=useState('Feedback'),[modal,setModal]=useState(null),[selected,setSelected]=useState(()=>new URLSearchParams(window.location.search).get('feedback')),[person,setPerson]=useState(null),[busy,setBusy]=useState(false),[toast,setToast]=useState(null),[loaded,setLoaded]=useState(false),[loadError,setLoadError]=useState(''),[demo,setDemo]=useState(true),[demoData,setDemoData]=useState(()=>structuredClone(sample));
  const view=demo?demoData:data;
  const notify=useCallback((message,type='success')=>setToast({id:crypto.randomUUID(),message,type}),[]);
  const closeToast=useCallback(()=>setToast(null),[]);
  const load=useCallback(async()=>{
    setLoaded(false);setLoadError('');
    try{const response=await fetch('api.php?route=board');if(!response.ok)throw Error('Could not connect to the workspace. Please try again.');const result=await response.json();setData(result.data);setRevision(result.revision);if(result.data.projects.length){setDemo(false);const linked=result.data.items.find(i=>i.id===new URLSearchParams(window.location.search).get('feedback'));setProject(linked?.project||result.data.projects[0].id);}setLoaded(true);}
    catch(error){setLoadError(error.message);setLoaded(true);}
  },[]);
  useEffect(()=>{load();},[load]);
  const activePerson=person?.kind==='developer'?data.developers.find(d=>d.id===person.id)?{...person,name:data.developers.find(d=>d.id===person.id).name}:null:person;
  async function save(next,message='Changes saved.'){
    if(busy)return false;
    setBusy(true);
    try{
      const response=await fetch('api.php?route=board',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({data:next,revision})});const result=await response.json();
      if(!response.ok){if(response.status===409){setData(result.data);setRevision(result.revision);}throw Error(result.error||'Unable to save changes.');}
      setData(next);setRevision(result.revision);
      if(result.notifications?.failed)notify(message+' '+(result.notifications.status==='not_configured'?'Assignment email was not sent: email setup is incomplete.':result.notifications.message||'The assignment email could not be sent.'),'warning');
      else notify(message+(result.notifications?.sent?' Assignment email accepted by the mail server.':''));
      return true;
    }catch(error){notify(error.message,'error');return false;}finally{setBusy(false);}
  }
  async function persist(next,message){if(demo){notify('Create your own project to save real feedback.','warning');return false;}return save(next,message);}
  async function updateItem(item,message='Feedback updated.'){
    if(demo){setDemoData(current=>({...current,items:current.items.map(t=>t.id===item.id?item:t)}));notify(message+' (sample)');return true;}
    return persist({...data,items:data.items.map(t=>t.id===item.id?item:t)},message);
  }
  async function createFeedback(form){
    if(busy)return;
    setBusy(true);
    try{
      if(!form.title||!form.comment||!form.file)throw Error('Add a screenshot, title, and comments.');
      const response=await fetch('api.php?route=upload',{method:'POST',headers:{'Content-Type':form.file.type},body:form.file});const upload=await response.json();if(!response.ok)throw Error(upload.error||'Upload failed.');
      const item={id:crypto.randomUUID(),project,title:form.title,comment:form.comment,developer:form.developer,image:upload.url,status:'Open',thread:[],createdAt:new Date().toISOString(),createdBy:activePerson?.name};
      // Upload and save remain one guarded operation.
      if(await saveBoardAfterUpload({...data,items:[item,...data.items]}))setModal(null);
    }catch(error){notify(error.message,'error');setBusy(false);}
  }
  async function saveBoardAfterUpload(next){
    try{const response=await fetch('api.php?route=board',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({data:next,revision})});const result=await response.json();if(!response.ok){if(response.status===409){setData(result.data);setRevision(result.revision);}throw Error(result.error||'Unable to save feedback.');}setData(next);setRevision(result.revision);notify('Feedback created.'+(result.notifications?.failed?' Feedback saved, but assignment email was not sent.':result.notifications?.sent?' Assignment email accepted by the mail server.':''),result.notifications?.failed?'warning':'success');return true;}catch(error){notify(error.message,'error');return false;}finally{setBusy(false);}
  }
  const selectedItem=view.items.find(i=>i.id===selected);
  const openFeedback=()=>setModal(demo?'project':'feedback');
  if(!introDone)return <LogoIntro onComplete={finishIntro}/>;
  if(!activePerson)return <><PersonPicker developers={data.developers} loading={!loaded} error={loadError} onRetry={load} onChoose={chosen=>{setPerson(chosen);notify('Welcome, '+chosen.name+'.');}}/><Toast toast={toast} onClose={closeToast}/></>;
  return <Shell page={page} setPage={setPage} person={activePerson} onSwitchPerson={()=>{setPerson(null);setModal(null);}} onValidation={message=>notify(message,'error')} projectMenu={<ProjectMenu projects={view.projects} project={project} onSelect={id=>{setProject(id);setPage('Feedback');notify('Project changed.');}} onCreate={()=>setModal('project')}/>}>
    {page==='Feedback'?<Board data={view} project={project} onNew={openFeedback} onDetail={setSelected} busy={busy} onAssign={(id,developer)=>{const name=view.developers.find(d=>d.id===developer)?.name;updateItem({...view.items.find(i=>i.id===id),developer},name?'Assigned to '+name+'.':'Developer unassigned.');}}/>:<Developers data={view} busy={busy} onSave={async developer=>{
      if(demo){notify('Create your own project first to manage your team.','warning');return false;}
      if(data.developers.some(d=>d.email===developer.email&&d.id!==developer.id)){notify('This email is already in your developer list.','error');return false;}
      return persist({...data,developers:developer.id?data.developers.map(d=>d.id===developer.id?developer:d):[...data.developers,{...developer,id:crypto.randomUUID()}]},developer.id?'Developer updated.':'Developer added.');
    }} onRemove={id=>persist({...data,developers:data.developers.filter(d=>d.id!==id),items:data.items.map(i=>i.developer===id?{...i,developer:''}:i)},'Developer removed; their feedback is now unassigned.')}/>}
    {demo&&<div className="sample-notice"><span><strong>Sample workspace</strong> · Explore the review flow, then start your own project.</span><button onClick={()=>setModal('project')}>Create your project</button></div>}
    {modal==='project'&&<ProjectForm onClose={()=>setModal(null)} busy={busy} onSave={async p=>{if(!p.name){notify('Enter a project name.','error');return;}const next={...data,projects:[...data.projects,{...p,id:crypto.randomUUID()}]};if(await save(next,'Project created.')){setDemo(false);setProject(next.projects.at(-1).id);setModal(null);setPage('Feedback');}}}/>}
    {modal==='feedback'&&<FeedbackForm developers={data.developers} onClose={()=>setModal(null)} busy={busy} onSave={createFeedback} onNotify={notify}/>}
    {selectedItem&&<Detail key={selectedItem.id} item={selectedItem} developers={view.developers} onClose={()=>setSelected(null)} busy={busy} onUpdate={updateItem} person={activePerson}/>}
    <Toast toast={toast} onClose={closeToast}/>
  </Shell>;
}
createRoot(document.getElementById('root')).render(<App/>);
