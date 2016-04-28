# Zumba amplitude-php

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/zumba/amplitude-php/master.svg?style=flat-square)](https://travis-ci.org/zumba/amplitude-php)
[![Code Coverage](https://img.shields.io/coveralls/zumba/amplitude-php/master.svg)](https://coveralls.io/github/zumba/amplitude-php)
[![Scrutinizer](https://scrutinizer-ci.com/g/zumba/amplitude-php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/zumba/amplitude-php/)

This is a moderately thin PHP API for [Amplitude](https://amplitude.com/), powerful enough to do what you need without getting in the way.  Designed to work well in 2 main scenarios:

* **Same user & Amplitude App** - When you are tracking possibly multiple events, all for the same user, all for the same Amplitude app.  This library provides a singleton method that allows persisting the user and API key.
* **Multiple Users / Same or Different Amplitude Apps** - For times you may need to log multiple events for a lot of different users, possibly using different amplitude apps.

# Work in Progress

We're still working documenting all the things and working out any kinks, but should have version 1.0.0 ready soon, stay tooned!
