(() => {
 const money = value => new Intl.NumberFormat('en-NG',{style:'currency',currency:'NGN',maximumFractionDigits:0}).format(value);
 let ticket=null;
 try { ticket=JSON.parse(localStorage.getItem('uiTechConfirmedTicket')||'null') || JSON.parse(localStorage.getItem('uiTechSelectedTicket')||'null'); } catch {}
 const verified=Boolean(ticket && ticket.paymentVerified===true);
 const get=(...vals)=>vals.find(v=>v!==undefined&&v!==null&&String(v).trim()!=='');
 const values={attendeeName:get(ticket?.attendeeName,ticket?.fullName,'Ticket details unavailable'),attendeeEmail:get(ticket?.attendeeEmail,ticket?.email,'—'),ticketReference:get(ticket?.ticketReference,ticket?.reference,'Not issued'),ticketType:get(ticket?.ticketType,ticket?.type,'Conference Pass'),quantity:Number(get(ticket?.quantity,1)),total:Number(get(ticket?.total,ticket?.totalAmount,ticket?.amount,0))};
 for(const [id,val] of Object.entries({attendeeName:values.attendeeName,attendeeEmail:values.attendeeEmail,ticketReference:values.ticketReference,ticketType:values.ticketType,passType:values.ticketType.toUpperCase(),ticketQuantity:values.quantity,ticketAmount:money(values.total)})){const el=document.getElementById(id);if(el)el.textContent=String(val)}
 const status=document.getElementById('ticketStatus'); if(status) status.textContent=verified?'PAYMENT VERIFIED':'PREVIEW ONLY';
 const notice=document.getElementById('demoNotice'); if(notice){notice.hidden=false;notice.textContent=verified?'Ticket details were loaded from browser storage; the backend must still validate the ticket at check-in.':'This is a frontend preview, not an issued ticket. A real ticket must be retrieved from the authenticated backend after payment verification.';}
 document.getElementById('printTicket')?.addEventListener('click',()=>window.print());
})();