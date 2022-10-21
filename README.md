"# GLPI-lacres" 
Este plugin foi criado a partir da necessidade de controlar os ativos de um domínio, sendo eles objetos do tipo 'computador'.
Na prática, o plugin, deve conseguir gerenciar os números dos lacres que são usados em computadores, identificando a data, o usuário, e o computador
que foi aplicado o lacre. A aplicação do lacre pode ser dos tipos: Quando atualiza o número do lacre, em um computador que já possuía um lacre; Quando o computador
não possuí lacre, ele relaciona o id do computador com o lacre, também acrescentando no histórico do computador; E por último, se necessário, controlar a substituição de 
lacre por outro com número distinto, atualizando também o histórico do computador.
A composição do numeral do lacre, é de 7 dígitos, não sendo possível, cadastrar lacres, com mais ou menos de 7 dígitos, sendo restritos apenas números, na sua validação.


Tela principal de chamados

![lacre-1_LI](https://user-images.githubusercontent.com/3485511/190216862-0e56486d-8a04-4b9a-bd68-d2e53d9adaf3.jpg)



Opçoes de ações do lacre para o computador
![Capturar-2](https://user-images.githubusercontent.com/3485511/190215941-e3ff6674-5f54-4063-b2c2-f00340ff9eff.PNG)

Histórico das alterações do lacre


![Capturar-3_LI (3)](https://user-images.githubusercontent.com/3485511/190216744-bbbfb202-dd62-4047-9f7a-99922bb9cc10.jpg)
