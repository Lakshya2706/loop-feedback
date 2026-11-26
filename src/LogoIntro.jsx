import React,{useEffect} from 'react';
export default function LogoIntro({onComplete}){
 useEffect(()=>{const timer=setTimeout(onComplete,2200);return()=>clearTimeout(timer);},[onComplete]);
 return <div className="logo-intro" role="status" aria-label="Welcome to Loop"><div className="intro-lockup"><svg className="intro-mark" viewBox="0 0 120 60" fill="none" aria-hidden="true"><path className="intro-guide" d="M60 30C46 9 38 8 28 8C15 8 7 18 7 30S15 52 28 52C38 52 46 51 60 30C74 9 82 8 92 8C105 8 113 18 113 30S105 52 92 52C82 52 74 51 60 30"/><path className="intro-draw" pathLength="1" d="M60 30C46 9 38 8 28 8C15 8 7 18 7 30S15 52 28 52C38 52 46 51 60 30C74 9 82 8 92 8C105 8 113 18 113 30S105 52 92 52C82 52 74 51 60 30"/></svg><span>Loop</span></div><button className="intro-skip" onClick={onComplete}>Skip intro</button></div>;
}
