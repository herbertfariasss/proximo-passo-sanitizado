import React from 'react';
import { createRoot } from 'react-dom/client';
import {
  Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Pie, PieChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis
} from 'recharts';

// ChartContainer e tooltip seguem a composição dos gráficos shadcn/ui.
function ChartContainer({ children }) {
  return <div className="admin-chart-container"><ResponsiveContainer width="100%" height="100%">{children}</ResponsiveContainer></div>;
}

function ChartTooltipContent({ active, payload, label, money = false }) {
  if (!active || !payload?.length) return null;
  return <div className="admin-chart-tooltip"><strong>{label ? formatDay(label) : payload[0]?.name}</strong>{payload.map((item, index) => <div key={`${item.dataKey}-${index}`}><i style={{ background: item.color || item.payload?.fill }} />{item.name}: <b>{money ? formatUsd(item.value) : Number(item.value).toLocaleString('pt-BR')}</b></div>)}</div>;
}

function formatDay(value) {
  const [, month, day] = String(value).split('-');
  return `${day}/${month}`;
}
function formatUsd(value) {
  return 'US$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 6 });
}

function Charts({ data }) {
  const series = data.chart || [];
  const online = Number(data.summary?.online || 0);
  const offline = Number(data.summary?.offline || 0);
  const presence = [{ name: 'Online', value: online, fill: '#8b82f5' }, { name: 'Desconectados', value: offline, fill: '#605c78' }];
  const completed = Number(data.summary?.tasks_completed || 0);
  const pending = Math.max(0, Number(data.summary?.tarefas || 0) - completed);
  const tasks = [{ name: 'Concluídas', value: completed, fill: '#43c6ad' }, { name: 'Não concluídas', value: pending, fill: '#605c78' }];
  const tick = { fill: 'var(--text-muted)', fontSize: 11 };
  const axis = { axisLine: false, tickLine: false, tick: tick };
  return <div className="admin-charts-grid">
    <section className="admin-chart-card admin-chart-wide"><div className="admin-chart-title"><div><h2>Atividade do sistema</h2><p>Pessoas ativas, novos cadastros e decisões por dia</p></div><div className="admin-chart-legend"><span><i className="users" />Cadastros</span><span><i className="decisions" />Decisões</span><span><i className="active" />Ativos</span></div></div><div className="admin-chart-facts"><span><b>{data.summary?.active_today ?? 0}</b> ativos hoje</span><span><b>{data.summary?.novos_usuarios ?? 0}</b> novos usuários</span><span><b>{data.summary?.decisoes ?? 0}</b> decisões no período</span></div><ChartContainer><AreaChart data={series} margin={{ top: 12, right: 12, left: -20, bottom: 0 }} accessibilityLayer><defs><linearGradient id="usersGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#8b82f5" stopOpacity=".32" /><stop offset="100%" stopColor="#8b82f5" stopOpacity="0" /></linearGradient><linearGradient id="decisionsGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#43c6ad" stopOpacity=".24" /><stop offset="100%" stopColor="#43c6ad" stopOpacity="0" /></linearGradient></defs><CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 5" /><XAxis dataKey="date" tickFormatter={formatDay} minTickGap={25} {...axis} /><YAxis allowDecimals={false} {...axis} /><Tooltip content={<ChartTooltipContent />} /><Area dataKey="usuarios" name="Cadastros" type="monotone" stroke="#8b82f5" strokeWidth={2.5} fill="url(#usersGradient)" /><Area dataKey="decisoes" name="Decisões" type="monotone" stroke="#43c6ad" strokeWidth={2.5} fill="url(#decisionsGradient)" /><Area dataKey="ativos" name="Ativos" type="monotone" stroke="#f2b15c" strokeWidth={2.5} fill="none" /></AreaChart></ChartContainer></section>
    <section className="admin-chart-card"><div className="admin-chart-title"><div><h2>Custo da IA</h2><p>Estimativa diária em USD</p></div></div><div className="admin-chart-facts"><span>Média por chamada: <b>{data.summary?.ai_average_usd == null ? '—' : formatUsd(data.summary.ai_average_usd)}</b></span></div><ChartContainer><BarChart data={series} margin={{ top: 12, right: 8, left: -18, bottom: 0 }} accessibilityLayer><CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 5" /><XAxis dataKey="date" tickFormatter={formatDay} minTickGap={25} {...axis} /><YAxis tickFormatter={(v) => '$' + Number(v).toFixed(3)} width={58} {...axis} /><Tooltip content={<ChartTooltipContent money />} /><Bar dataKey="custo" name="Custo" fill="#8b82f5" radius={[5, 5, 0, 0]} maxBarSize={26} /></BarChart></ChartContainer></section>
    <section className="admin-chart-card"><div className="admin-chart-title"><div><h2>Presença atual</h2><p>Atividade nos últimos 5 minutos</p></div></div><div className="admin-presence-chart"><ChartContainer><PieChart accessibilityLayer><Pie data={presence} dataKey="value" nameKey="name" innerRadius="64%" outerRadius="86%" stroke="none" paddingAngle={presence.every(item => item.value > 0) ? 3 : 0}>{presence.map(item => <Cell key={item.name} fill={item.fill} />)}</Pie><Tooltip content={<ChartTooltipContent />} /></PieChart></ChartContainer><div className="admin-presence-center"><strong>{online + offline}</strong><span>usuários</span></div></div><div className="admin-chart-legend presence"><span><i className="users" />Online: {online}</span><span><i className="offline" />Desconectados: {offline}</span></div></section>
    <section className="admin-chart-card"><div className="admin-chart-title"><div><h2>Planejamento</h2><p>Tarefas e objetivos criados no período</p></div></div><div className="admin-chart-facts"><span><b>{data.summary?.objetivos ?? 0}</b> objetivos · progresso médio <b>{Number(data.summary?.goal_average_progress || 0).toLocaleString('pt-BR')}%</b></span></div><div className="admin-presence-chart"><ChartContainer><PieChart accessibilityLayer><Pie data={tasks} dataKey="value" nameKey="name" innerRadius="64%" outerRadius="86%" stroke="none" paddingAngle={tasks.every(item => item.value > 0) ? 3 : 0}>{tasks.map(item => <Cell key={item.name} fill={item.fill} />)}</Pie><Tooltip content={<ChartTooltipContent />} /></PieChart></ChartContainer><div className="admin-presence-center"><strong>{Number(data.summary?.task_completion_rate || 0).toLocaleString('pt-BR')}%</strong><span>tarefas concluídas</span></div></div><div className="admin-chart-legend presence"><span><i className="decisions" />Concluídas: {completed}</span><span><i className="offline" />Não concluídas: {pending}</span></div></section>
  </div>;
}

const target = document.getElementById('admin-charts');
const root = target ? createRoot(target) : null;
window.renderAdminCharts = (data) => { if (root) root.render(<Charts data={data} />); };
window.dispatchEvent(new Event('admin-charts-ready'));
