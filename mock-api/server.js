const express = require('express');
const cors = require('cors');

const app = express();
const port = 3000;

// Middleware
app.use(cors());
app.use(express.json());

// Rota GET para teste no navegador
app.get('/validar-cpf', (req, res) => {
    console.log("Requisição GET recebida em /validar-cpf");
    res.json({ message: "API ativa! Utilize POST para validar o CPF." });
});

// Rota POST para validar CPF
app.post('/validar-cpf', (req, res) => {
    const { cpf } = req.body;

    console.log('Requisição POST recebida:', req.body); // Log da requisição recebida

    if (!cpf) {
        console.error('CPF é obrigatório');
        return res.status(400).json({ error: "CPF é obrigatório" });
    }

    // Lógica fictícia de validação de CPF
    const isValid = cpf === "12345678901"; // Exemplo de CPF válido
    console.log('Resultado da validação:', isValid ? 'CPF válido' : 'CPF inválido');

    res.json({ valid: isValid });
});

// Inicializando o servidor
app.listen(port, () => {
    console.log(`Servidor rodando em http://localhost:${port}`);
});
