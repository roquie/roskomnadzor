# Парсер дампа запрещенных доменов

Простейшая консольная утилита для сбора IP-адресов из XML дампа роскомнадзора (версия 2.2). Делает всего 2 вещи:
* Считывает все IP-адреса в файле и записывает по порядку в другой файл (по умолчанию)
* Считывает домены и по каждому обращается к указанным DNS-серверам получая его IP-адрес. Результат так-же в отдельном файле.

### Зависимости

* php >= 7.0

### Установка

Можно использовать `phar` архив напрямую: <br>
`php roskomnadzor.phar --version`

или установить в систему <br>
1. `chmod +x roskomnadzor.phar`
2. `sudo mv roskomnadzor.phar /usr/local/bin/roskomnadzor`
3. `roskomnadzor --version`

### Пример использования

1. Просто считать IP-адреса указанные в файле:

```bash
$ roskomnadzor dump:ip-list --source=dump.xml --destination=iplist.txt
```

2. Получить IP-адрес используя DNS-сервер:

```bash
$ roskomnadzor dump:ip-list --resolve -s=dump.xml -d=iplist.txt --logfile=err.log
```

или (по умолчанию `8.8.8.8`)

```bash
$ roskomnadzor dump:ip-list -r --nameservers=8.8.8.8,8.8.4.4 -s=dump.xml -d=iplist.txt --logfile=err.log
```

3. Выключить запись логов неудачных попыток получить IP-адрес:
 
```bash
$ roskomnadzor dump:ip-list -r -s=dump.xml -d=iplist.txt --log-disable
``` 

4. Убрать прогресс-бар:

```bash
$ roskomnadzor dump:ip-list -r -s=dump.xml -d=iplist.txt --no-progress
``` 

или

```bash
$ roskomnadzor dump:ip-list -r -s=dump.xml -d=iplist.txt -fast
``` 

### Тесты

На данном этапе не предусмотрены.

### Лицензия

MIT
