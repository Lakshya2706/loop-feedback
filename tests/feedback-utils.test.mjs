import test from 'node:test';
import assert from 'node:assert/strict';
import {feedbackTabs,filterFeedback,latestReply,personComment} from '../src/feedback-utils.js';
test('Open is first and All feedback last; filters compose',()=>{
 assert.deepEqual(feedbackTabs,['Open','Ready for review','Approved','All feedback']);
 const items=[{id:'one',project:'p',status:'Open',developer:'a',title:'Navigation',comment:'Fix menu'},{id:'two',project:'p',status:'Approved',developer:'',title:'Form',comment:'Spacing'},{id:'three',project:'q',status:'Open',developer:'a',title:'Other',comment:''}];
 assert.deepEqual(filterFeedback(items,{project:'p',developer:'a',query:'menu'}).map(i=>i.id),['one']);
 assert.deepEqual(filterFeedback(items,{project:'p',status:'All feedback',developer:'__unassigned__'}).map(i=>i.id),['two']);
});
test('legacy Reviewer comments remain visible and are not relabeled as developers',()=>{
 const legacy={thread:[{role:'Reviewer',text:'Existing discussion'},{role:'Activity',text:'Feedback reopened'}]};const snapshot=JSON.stringify(legacy);
 assert.equal(latestReply(legacy).entry.text,'Existing discussion');assert.equal(latestReply(legacy).isDiscussion,true);assert.equal(JSON.stringify(legacy),snapshot);
 assert.equal(latestReply({thread:[{role:'Activity',text:'Done'}]}),null);
 const modern={thread:[{role:'Developer',authorName:'Alice',text:'Fixed'},{role:'Reviewer',text:'Thanks'}]};assert.equal(latestReply(modern).entry.text,'Fixed');assert.equal(latestReply(modern).isDiscussion,false);
});
test('selected person determines role and attribution for new comments',()=>{
 assert.deepEqual(personComment({id:'a',name:'Alice',kind:'developer'},' Done ','c','date'),{id:'c',role:'Developer',authorId:'a',authorName:'Alice',text:'Done',at:'date'});
 assert.equal(personComment({id:'admin',name:'Admin',kind:'admin'},'Check','c','date').role,'Reviewer');
});
