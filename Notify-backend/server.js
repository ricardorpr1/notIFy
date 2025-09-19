// server.js
// npm i express pg cors dotenv
const express = require('express');
const cors = require('cors');
const { Pool } = require('pg');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.static('public')); // se index.html estiver em /public

// Configurar pool do Postgres - substitua pelos seus dados ou use .env
const pool = new Pool({
  host: process.env.PGHOST || 'SEU_HOST_AQUI',
  user: process.env.PGUSER || 'SEU_USUARIO_AQUI',
  password: process.env.PGPASSWORD || 'SUA_SENHA_AQUI',
  database: process.env.PGDATABASE || 'SEU_BANCO_AQUI',
  port: process.env.PGPORT ? parseInt(process.env.PGPORT) : 5432,
  max: 10
});

// Endpoint para criar evento
app.post('/api/events', async (req, res) => {
  try {
    const {
      nome,
      descricao,
      local,
      horario_inicio,
      horario_fim,
      icone_url,
      capa_url,
      limite_participantes,
      turmas_permitidas,
      colaboradores,
      data_evento
    } = req.body;

    // Validações mínimas
    if (!nome || !data_evento) {
      return res.status(400).json({ error: 'Campos obrigatórios faltando: nome e data_evento' });
    }

    // Query parametrizada
    const insertSQL = `
      INSERT INTO eventos
      (nome, descricao, local, horario_inicio, horario_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores, data_evento)
      VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11)
      RETURNING id, nome, data_evento;
    `;

    // pass arrays as JS arrays -> pg will map to TEXT[]
    const values = [
      nome,
      descricao || null,
      local || null,
      horario_inicio || null, // should be 'HH:MM:SS' or null
      horario_fim || null,
      icone_url || null,
      capa_url || null,
      limite_participantes || null,
      (Array.isArray(turmas_permitidas) && turmas_permitidas.length > 0) ? turmas_permitidas : null,
      (Array.isArray(colaboradores) && colaboradores.length > 0) ? colaboradores : null,
      data_evento // 'YYYY-MM-DD'
    ];

    const result = await pool.query(insertSQL, values);
    const inserted = result.rows[0];

    res.status(201).json({ id: inserted.id, nome: inserted.nome, data_evento: inserted.data_evento });
  } catch (err) {
    console.error('Erro ao inserir evento:', err);
    res.status(500).json({ error: 'Erro ao inserir evento' });
  }
});

// exemplo healthcheck
app.get('/api/ping', (req, res) => res.json({ ok: true }));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Servidor rodando na porta ${PORT}`));
