function g(t,y,r){const u={};for(let e=0;e<t.length;e++){let c=t[e];const l=y(c),a=u[l];c=r!=null&&r.map?r.map(t[e],e):t[e],Array.isArray(a)?u[l].push(c):u[l]=[c]}return u}export{g};
