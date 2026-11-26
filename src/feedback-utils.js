export const feedbackTabs = ['Open', 'Ready for review', 'Approved', 'All feedback'];
export function latestReply(item) {
  const comments = (item.thread || []).filter(entry => entry.role !== 'Activity' && entry.text?.trim());
  const developer = comments.filter(entry => entry.role === 'Developer').at(-1);
  return developer ? {entry: developer, isDiscussion: false} : comments.length ? {entry: comments.at(-1), isDiscussion: true} : null;
}
export function filterFeedback(items, {project, status = 'Open', developer = '', query = ''}) {
  const search = query.trim().toLowerCase();
  return items.filter(item => item.project === project &&
    (status === 'All feedback' || item.status === status) &&
    (!developer || (developer === '__unassigned__' ? !item.developer : item.developer === developer)) &&
    `${item.title} ${item.comment}`.toLowerCase().includes(search))
    .sort((a,b)=>(Date.parse(b.createdAt)||0)-(Date.parse(a.createdAt)||0));
}
export function personComment(person, text, id, at) {
  return {id, role: person.kind === 'developer' ? 'Developer' : 'Reviewer', authorId: person.id, authorName: person.name, text: text.trim(), at};
}
