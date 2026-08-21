(() => {
'use strict';
const root=document.querySelector('[data-marine-map]');
if(!root||!window.L)return;
const map=L.map(root.id,{zoomControl:true}).setView([49.3,-123.3],7);
const groups={
 weather:L.layerGroup().addTo(map),marine:L.layerGroup().addTo(map),alerts:L.layerGroup().addTo(map),
 stations:L.layerGroup(),earthquakes:L.layerGroup().addTo(map),vessels:L.layerGroup().addTo(map),ais:L.layerGroup().addTo(map)
};
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
groups.radar=L.tileLayer.wms(root.dataset.radarWms,{layers:root.dataset.radarLayer,format:'image/png',transparent:true,opacity:.58,version:'1.3.0'}).addTo(map);
['temperature','cloud','wind','gust','precipitation'].forEach(key=>{
 groups[key]=L.tileLayer.wms(root.dataset.radarWms,{layers:root.dataset[key+'Layer'],format:'image/png',transparent:true,opacity:.62,version:'1.3.0'});
});

const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const n=(v,d=1)=>Number.isFinite(Number(v))?Number(v).toFixed(d):'—';
const time=v=>v?new Date(v).toLocaleString():'No successful import recorded';
const coords=p=>{
 const g=p.geometry||p.geoJSON?.geometry;if(g?.type==='Point')return[Number(g.coordinates[1]),Number(g.coordinates[0])];
 const q=p.properties||p,lat=Number(q.latitude??q.lat??p.requested_latitude),lon=Number(q.longitude??q.lon??q.lng??p.requested_longitude);
 return Number.isFinite(lat)&&Number.isFinite(lon)?[lat,lon]:null;
};
const title=p=>p.properties?.headline||p.properties?.event||p.properties?.title||p.location_name||p.officialName||p.name||'Marine record';
const distanceNm=(a,b)=>{const r=3440.065,p1=a[0]*Math.PI/180,p2=b[0]*Math.PI/180,dp=(b[0]-a[0])*Math.PI/180,dl=(b[1]-a[1])*Math.PI/180;return 2*r*Math.asin(Math.sqrt(Math.sin(dp/2)**2+Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2));};
const weatherName=code=>({0:'Clear',1:'Mainly clear',2:'Partly cloudy',3:'Overcast',45:'Fog',48:'Rime fog',51:'Light drizzle',53:'Drizzle',55:'Heavy drizzle',61:'Light rain',63:'Rain',65:'Heavy rain',71:'Light snow',73:'Snow',75:'Heavy snow',80:'Rain showers',81:'Rain showers',82:'Heavy showers',95:'Thunderstorm',96:'Thunderstorm with hail',99:'Severe thunderstorm with hail'})[code]||'Weather code '+code;

function sourceStatus(sources){
 document.querySelectorAll('[data-source-status]').forEach(list=>{
  list.replaceChildren();
  Object.values(sources||{}).forEach(source=>{
   const item=document.createElement('li');
   const label=document.createElement('strong');
   const state=document.createElement('span');
   const timestamp=document.createElement('span');
   label.textContent=source.label+': ';
   state.textContent = source.status;
   state.className='source-state source-state-'+source.status;
   timestamp.textContent=' — imported '+time(source.retrieved_at)+' · '+source.record_count+' record(s)';
   item.append(label,state,timestamp);
   if(source.documentation){const link=document.createElement('a');link.href=source.documentation;link.textContent='source documentation';link.rel='noopener noreferrer';item.append(' · ',link);}
   list.append(item);
  });
 });
}
function report(selector,html){document.querySelectorAll(selector).forEach(el=>el.innerHTML=html);}
function reportSummary(key,text,state='current'){document.querySelectorAll('[data-report-summary="'+key+'"]').forEach(el=>{el.textContent=text;el.className='source-state source-state-'+state;});}
function geo(feature,group,style){
 if(!feature.geometry)return;
 L.geoJSON(feature,{style,pointToLayer:(_f,ll)=>L.circleMarker(ll,style),onEachFeature:(f,l)=>l.bindPopup('<strong>'+esc(title(f))+'</strong><br>'+esc(f.properties?.description||f.properties?.severity||''))}).addTo(group);
}
function forecastRows(forecast,fields,count=48){
 const h=forecast.hourly||{},units=forecast.hourly_units||{},times=h.time||[];
 return times.slice(0,count).map((t,i)=>'<tr><td>'+esc(new Date(t).toLocaleString([], {weekday:'short',hour:'numeric'}))+'</td>'+fields.map(k=>'<td>'+esc(n(h[k]?.[i]))+' '+esc(units[k]||'')+'</td>').join('')+'</tr>').join('');
}

fetch(root.dataset.endpoint,{headers:{Accept:'application/json'},credentials:'same-origin'})
.then(r=>{if(!r.ok)throw new Error('Map data request failed');return r.json();})
.then(data=>{
 sourceStatus(data.sources);
 data.weather_alerts.forEach(f=>geo(f,groups.alerts,{color:'#ffb347',weight:3,fillOpacity:.15,radius:8}));
 data.water_level_stations.forEach(s=>{const c=coords(s);if(!c)return;L.circleMarker(c,{radius:5,color:'#38d6c6'}).bindPopup('<strong>'+esc(title(s))+'</strong><br>Official CHS station metadata').addTo(groups.stations);});
 data.earthquakes.forEach(q=>{const c=coords(q);if(!c)return;const tsunami=Number(q.properties?.tsunami)===1;L.circleMarker(c,{radius:Math.max(4,Number(q.properties?.mag||0)*2),color:tsunami?'#ff4d4d':'#ffe08a',fillOpacity:.45}).bindPopup('<strong>'+esc(title(q))+'</strong><br>Magnitude '+esc(q.properties?.mag)+'<br>'+(tsunami?'USGS tsunami flag present — verify official warnings.':'No USGS tsunami flag.')).addTo(groups.earthquakes);});
 data.vessels.forEach(v=>{if(v.latitude===null||v.longitude===null)return;const c=[Number(v.latitude),Number(v.longitude)];L.marker(c).bindPopup('<strong>'+esc(v.name)+'</strong><br>Speed '+esc(v.speed_knots??'—')+' kn · Course '+esc(v.course_degrees??'—')+'°<br>Position '+esc(time(v.recorded_at))).addTo(groups.vessels);});

 const ownPosition=data.vessels.find(v=>v.latitude!==null&&v.longitude!==null);
 const ownCoords=ownPosition?[Number(ownPosition.latitude),Number(ownPosition.longitude)]:null;
 data.weather_forecasts.forEach(item=>{const c=coords(item);item._distance_nm=ownCoords&&c?distanceNm(ownCoords,c):null;});
 data.weather_forecasts.sort((a,b)=>(a._distance_nm??Number.MAX_VALUE)-(b._distance_nm??Number.MAX_VALUE));
 let weatherHtml='';
 data.weather_forecasts.forEach((item,index)=>{
  const f=item.forecast||{},c=coords(item),cur=f.current||{},u=f.current_units||{},nearest=index===0&&ownCoords;
  if(c)L.circleMarker(c,{radius:8,color:'#7ec8ff',fillColor:'#287db5',fillOpacity:.8}).bindPopup('<strong>'+esc(item.location_name)+'</strong><br>'+esc(weatherName(cur.weather_code))+' · '+esc(n(cur.temperature_2m))+' '+esc(u.temperature_2m||'°C')+'<br>Wind '+esc(n(cur.wind_speed_10m))+' '+esc(u.wind_speed_10m||'kn')+' gust '+esc(n(cur.wind_gusts_10m))+'<br>Imported '+esc(time(item._marine_retrieved_at))).addTo(groups.weather);
  const d=f.daily||{},du=f.daily_units||{},dailyRows=(d.time||[]).map((t,i)=>'<tr><td>'+esc(new Date(t+'T12:00:00').toLocaleDateString([],{weekday:'short',month:'short',day:'numeric'}))+'</td><td>'+esc(weatherName(d.weather_code?.[i]))+'</td><td>'+esc(n(d.temperature_2m_min?.[i]))+'–'+esc(n(d.temperature_2m_max?.[i]))+' '+esc(du.temperature_2m_max||'°C')+'</td><td>'+esc(n(d.precipitation_probability_max?.[i],0))+'%</td><td>'+esc(n(d.wind_gusts_10m_max?.[i]))+' '+esc(du.wind_gusts_10m_max||'kn')+'</td><td>'+esc(d.sunrise?.[i]||'—')+' / '+esc(d.sunset?.[i]||'—')+'</td></tr>').join('');
  const risk=Number(cur.cape)>=1000?'Elevated convective/lightning potential':Number(cur.cape)>=300?'Some convective potential':'No elevated convective signal in this model point';
  weatherHtml+='<article class="condition-card"><h4>'+esc(item.location_name)+(nearest?' <span class="nearest-badge">Nearest to '+esc(ownPosition.name)+'</span>':'')+'</h4><p><strong>'+esc(weatherName(cur.weather_code))+'</strong> · '+esc(n(cur.temperature_2m))+' '+esc(u.temperature_2m||'°C')+' (feels '+esc(n(cur.apparent_temperature))+') · Humidity '+esc(n(cur.relative_humidity_2m,0))+'% · Pressure '+esc(n(cur.pressure_msl))+' '+esc(u.pressure_msl||'hPa')+' · Cloud '+esc(n(cur.cloud_cover,0))+'% · Visibility '+esc(n(Number(cur.visibility)/1000))+' km</p><p>Wind '+esc(n(cur.wind_speed_10m))+' '+esc(u.wind_speed_10m||'kn')+' from '+esc(n(cur.wind_direction_10m,0))+'° · Gust '+esc(n(cur.wind_gusts_10m))+' · '+esc(risk)+' (CAPE '+esc(n(cur.cape,0))+' J/kg)'+(item._distance_nm!==null?' · '+esc(n(item._distance_nm))+' NM from vessel':'')+'</p><div class="table-scroll"><table><thead><tr><th>Day</th><th>Conditions</th><th>Low–high</th><th>Rain</th><th>Max gust</th><th>Sunrise / sunset</th></tr></thead><tbody>'+dailyRows+'</tbody></table></div><details><summary>Show full 48-hour forecast</summary><div class="table-scroll"><table><thead><tr><th>Time</th><th>Temp</th><th>Rain %</th><th>Wind</th><th>Gust</th><th>Visibility</th><th>CAPE</th></tr></thead><tbody>'+forecastRows(f,['temperature_2m','precipitation_probability','wind_speed_10m','wind_gusts_10m','visibility','cape'])+'</tbody></table></div></details><p class="import-time">Forecast model time '+esc(time(cur.time))+' · Imported '+esc(time(item._marine_retrieved_at))+'</p></article>';
 });
 report('[data-weather-report]',weatherHtml||'<p class="degraded"><strong>Unavailable:</strong> no weather forecast has completed import yet. This does not indicate safe conditions.</p>');
 reportSummary('weather',data.weather_forecasts.length?data.weather_forecasts.length+' location(s) · '+data.sources.open_meteo_weather.status:'Unavailable',data.sources.open_meteo_weather.status);

 let marineHtml='';
 data.marine_forecasts.forEach(item=>{
  const f=item.forecast||{},c=coords(item),cur=f.current||{},u=f.current_units||{};
  if(c)L.circleMarker(c,{radius:8,color:'#38d6c6',fillColor:'#0d6f7a',fillOpacity:.8}).bindPopup('<strong>'+esc(item.location_name)+'</strong><br>Wave '+esc(n(cur.wave_height))+' '+esc(u.wave_height||'m')+' @ '+esc(n(cur.wave_period))+' s<br>Current '+esc(n(cur.ocean_current_velocity))+' '+esc(u.ocean_current_velocity||'kn')+' toward '+esc(n(cur.ocean_current_direction,0))+'°<br>Sea '+esc(n(cur.sea_surface_temperature))+' '+esc(u.sea_surface_temperature||'°C')+'<br>Imported '+esc(time(item._marine_retrieved_at))).addTo(groups.marine);
  marineHtml+='<article class="condition-card"><h4>'+esc(item.location_name)+'</h4><p>Wave <strong>'+esc(n(cur.wave_height))+' '+esc(u.wave_height||'m')+'</strong> from '+esc(n(cur.wave_direction,0))+'° every '+esc(n(cur.wave_period))+' s · Swell '+esc(n(cur.swell_wave_height))+' '+esc(u.swell_wave_height||'m')+' · Current '+esc(n(cur.ocean_current_velocity))+' '+esc(u.ocean_current_velocity||'kn')+' toward '+esc(n(cur.ocean_current_direction,0))+'° · Sea '+esc(n(cur.sea_surface_temperature))+' '+esc(u.sea_surface_temperature||'°C')+'</p><div class="table-scroll"><table><thead><tr><th>Time</th><th>Wave</th><th>Period</th><th>Current</th><th>Sea level*</th></tr></thead><tbody>'+forecastRows(f,['wave_height','wave_period','ocean_current_velocity','sea_level_height_msl'])+'</tbody></table></div><p class="import-time">Imported '+esc(time(item._marine_retrieved_at))+'</p></article>';
 });
 report('[data-marine-report]',marineHtml||'<p class="degraded"><strong>Unavailable:</strong> no marine forecast has completed import yet.</p>');
 reportSummary('marine',data.marine_forecasts.length?data.marine_forecasts.length+' location(s) · '+data.sources.open_meteo_marine.status:'Unavailable',data.sources.open_meteo_marine.status);

 const tideModels=data.marine_forecasts.map(item=>{const c=item.forecast?.current||{},u=item.forecast?.current_units||{};return '<article class="condition-card"><h4>'+esc(item.location_name)+'</h4><p>Modelled sea level: <strong>'+esc(n(c.sea_level_height_msl))+' '+esc(u.sea_level_height_msl||'m')+'</strong> relative to global mean sea level.</p><p class="import-time">Imported '+esc(time(item._marine_retrieved_at))+'</p></article>';}).join('');
 const paidTides=data.paid_tide_forecasts.length?'<p>'+esc(data.paid_tide_forecasts.length)+' user-funded WorldTides record(s) imported. See source freshness below.</p>':'<p>No user-funded WorldTides forecast is configured.</p>';
 report('[data-tide-report]',(tideModels||'<p class="degraded">No modelled water-level data imported.</p>')+paidTides+'<p><strong>*Not for coastal navigation:</strong> model resolution and datum differ from official CHS predictions and observations.</p>');
 reportSummary('tides',(data.marine_forecasts.length+data.paid_tide_forecasts.length)+' report(s)',data.marine_forecasts.length?'current':'unavailable');

 const bulletins=(data.official_marine_bulletins||[]).slice(0,20).map(b=>'<article class="alert-row official-bulletin"><strong>ECCC Pacific marine bulletin</strong> · '+esc(b.filename||'')+'<details><summary>Read official bulletin text</summary><p>'+esc(b.text||'No bulletin text extracted.')+'</p><p><a href="'+esc(b.source_url||'#')+'" rel="noopener noreferrer">Original ECCC XML</a></p></details><span class="import-time">Issued '+esc(b.issued_at||'—')+' · Imported '+esc(time(b._marine_retrieved_at))+'</span></article>').join('');
 const alerts=data.weather_alerts.slice(0,12).map(a=>'<article class="alert-row"><strong>'+esc(title(a))+'</strong><br>'+esc(a.properties?.severity||a.properties?.description||'Official alert record')+'<br><span class="import-time">Imported '+esc(time(a._marine_retrieved_at))+'</span></article>').join('');
 report('[data-alert-report]',bulletins+alerts||'<p>No official marine bulletin or alert records are imported. This does not mean there are no official warnings.</p>');
 reportSummary('alerts',(data.official_marine_bulletins.length+data.weather_alerts.length)+' bulletin/alert record(s)',(data.official_marine_bulletins.length+data.weather_alerts.length)?'current':'unavailable');

 (data.ais.vessels||[]).forEach(record=>{const a=record.AIS||record.ais||record,lat=Number(a.LATITUDE??a.latitude),lon=Number(a.LONGITUDE??a.longitude);if(!Number.isFinite(lat)||!Number.isFinite(lon))return;L.circleMarker([lat,lon],{radius:6,color:'#8ab4ff',fillOpacity:.75}).bindPopup('<strong>'+esc(a.NAME||a.name||a.MMSI||'AIS vessel')+'</strong><br>MMSI '+esc(a.MMSI||'—')+' · '+esc(a.SPEED??'—')+' kn<br>Position '+esc(a.TIMESTAMP||'time unavailable')).addTo(groups.ais);});
 document.querySelectorAll('[data-ais-status]').forEach(el=>{el.textContent=data.ais.message;el.className='source-state source-state-'+data.ais.status;});
 document.querySelectorAll('[data-map-updated]').forEach(el=>el.textContent='Map response generated '+new Date(data.generated_at).toLocaleString());
}).catch(error=>{
 document.querySelectorAll('[data-map-updated]').forEach(el=>{el.textContent=error.message;el.classList.add('error-text');});
 report('[data-weather-report],[data-marine-report],[data-tide-report],[data-alert-report]','<p class="error">Conditions could not be loaded. Use official sources.</p>');
});

document.querySelectorAll('[data-layer]').forEach(input=>input.addEventListener('change',()=>{const layer=groups[input.dataset.layer];if(layer)(input.checked?layer.addTo(map):map.removeLayer(layer));}));
let planning=false,waypoints=[],routeLine=null;
const renderRoute=()=>{if(routeLine)map.removeLayer(routeLine);if(waypoints.length)routeLine=L.polyline(waypoints,{color:'#38d6c6',weight:4}).addTo(map);let nm=0;for(let i=1;i<waypoints.length;i++)nm+=map.distance(waypoints[i-1],waypoints[i])/1852;document.querySelectorAll('[data-route-summary]').forEach(el=>el.textContent=waypoints.length+' waypoint(s) · '+nm.toFixed(1)+' nautical miles');localStorage.setItem('marine.route.waypoints',JSON.stringify(waypoints.map(p=>[p.lat,p.lng])));};
try{waypoints=JSON.parse(localStorage.getItem('marine.route.waypoints')||'[]').map(p=>L.latLng(p[0],p[1]));renderRoute();}catch(_){}
map.on('click',e=>{if(planning){waypoints.push(e.latlng);renderRoute();}});
document.querySelectorAll('[data-map-action]').forEach(b=>b.addEventListener('click',()=>{if(b.dataset.mapAction==='plan'){planning=!planning;b.textContent=planning?'Stop planning':'Plan route';}if(b.dataset.mapAction==='undo'){waypoints.pop();renderRoute();}if(b.dataset.mapAction==='clear'){waypoints=[];renderRoute();}}));

const dialog=document.querySelector('[data-position-dialog]'),pickerRoot=document.querySelector('[data-position-picker-map]');
let picker,marker,selected;
const selectPosition=ll=>{selected=ll;if(!marker)marker=L.marker(ll,{draggable:true}).addTo(picker).on('dragend',e=>selectPosition(e.target.getLatLng()));else marker.setLatLng(ll);dialog.querySelector('[data-position-picker-coordinates]').textContent=ll.lat.toFixed(7)+', '+ll.lng.toFixed(7);dialog.querySelector('[data-apply-position]').disabled=false;};
document.querySelectorAll('[data-open-position-map]').forEach(button=>button.addEventListener('click',()=>{dialog.showModal();setTimeout(()=>{if(!picker){picker=L.map(pickerRoot).setView(map.getCenter(),map.getZoom());L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(picker);picker.on('click',e=>selectPosition(e.latlng));}picker.invalidateSize();const form=button.closest('form'),lat=Number(form.elements.latitude.value),lon=Number(form.elements.longitude.value);if(Number.isFinite(lat)&&Number.isFinite(lon)){selectPosition(L.latLng(lat,lon));picker.setView([lat,lon],13);}},50);}));
document.querySelectorAll('[data-apply-position]').forEach(button=>button.addEventListener('click',()=>{if(!selected)return;const form=document.querySelector('[data-open-position-map]').closest('form');form.elements.latitude.value=selected.lat.toFixed(7);form.elements.longitude.value=selected.lng.toFixed(7);dialog.close();}));
document.querySelectorAll('[data-use-location]').forEach(button=>button.addEventListener('click',()=>{if(!navigator.geolocation){alert('Geolocation is not supported by this browser.');return;}navigator.geolocation.getCurrentPosition(p=>{const form=button.closest('form');form.elements.latitude.value=p.coords.latitude;form.elements.longitude.value=p.coords.longitude;form.elements.accuracy_m.value=p.coords.accuracy;},()=>alert('Location could not be obtained. Check browser permission and enter it manually.'));}));
})();